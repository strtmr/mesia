<?php

declare(strict_types=1);

namespace App\Sync\Application;

use App\Shared\Application\ChangeLog\ChangeContext;
use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shared\Application\Sync\MutationRejected;
use App\Shared\Infrastructure\Persistence\HouseholdWriteLock;
use App\Sync\Domain\Model\ProcessedMutation;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Verarbeitet die Mutationen eines Sync-Push der Reihe nach, jede in einer eigenen Transaktion.
 *
 * - Idempotent: Bereits verarbeitete Mutationen (gleiche ID) liefern ihr gespeichertes Ergebnis als "duplicate".
 * - Jede Transaktion holt zuerst den HouseholdWriteLock (Reihenfolge im Änderungsprotokoll, siehe dort).
 * - Fachlich ungültige Mutationen werden "rejected" und ebenfalls gespeichert. Die übrigen laufen weiter.
 * - Liegt die Geräteuhr in der Zukunft, wird der Zeitpunkt auf "jetzt" begrenzt.
 */
final readonly class MutationDispatcher
{
    public function __construct(
        #[AutowireLocator(MutationHandler::TAG, defaultIndexMethod: 'mutationType')]
        private ContainerInterface $handlers,
        private EntityManagerInterface $entityManager,
        private HouseholdWriteLock $householdWriteLock,
        private ChangeContext $changeContext,
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private ManagerRegistry $doctrine,
    ) {
    }

    /**
     * @param list<IncomingMutation> $mutations
     *
     * @return list<array<string, mixed>>
     */
    public function dispatch(Uuid $householdId, Uuid $actorId, string $deviceId, array $mutations): array
    {
        $results = [];
        /** @var array<string, string> $redirects lokale ID → ID des bestehenden Objekts (nach "merged") */
        $redirects = [];

        foreach ($mutations as $mutation) {
            $mutation = self::redirect($mutation, $redirects);
            $result = $this->process($householdId, $actorId, $deviceId, $mutation);

            $localId = self::createdId($mutation);
            if (null !== $localId && \is_string($result['merged_into'] ?? null)) {
                $redirects[$localId] = $result['merged_into'];
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Folgemutationen im selben Push auf das zusammengeführte Objekt umlenken. Beispiel: offline "Milch" angelegt und
     * gleich abgehakt, das andere Gerät hatte Milch schon. Ohne Umlenkung würde das Abhaken abgelehnt.
     *
     * @param array<string, string> $redirects
     */
    private static function redirect(IncomingMutation $mutation, array $redirects): IncomingMutation
    {
        $payload = $mutation->payload;
        $changed = false;
        foreach (['item_id', 'article_id'] as $key) {
            if (\is_string($payload[$key] ?? null) && isset($redirects[$payload[$key]])) {
                $payload[$key] = $redirects[$payload[$key]];
                $changed = true;
            }
        }

        return $changed ? new IncomingMutation($mutation->id, $mutation->type, $mutation->at, $payload) : $mutation;
    }

    /**
     * ID des Objekts, das eine Mutation neu anlegt (und das deshalb zusammengeführt werden kann).
     */
    private static function createdId(IncomingMutation $mutation): ?string
    {
        $key = match ($mutation->type) {
            'item.add' => 'item_id',
            'article.create' => 'article_id',
            default => null,
        };
        $id = null === $key ? null : ($mutation->payload[$key] ?? null);

        return \is_string($id) ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function process(Uuid $householdId, Uuid $actorId, string $deviceId, IncomingMutation $mutation): array
    {
        $previous = $this->previousResult($householdId, $mutation->id);
        if (null !== $previous) {
            return $previous;
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->householdWriteLock->acquire($householdId);

            // Erneut prüfen, jetzt unter dem Lock: Ein paralleler Push derselben Mutation könnte schneller gewesen sein.
            $previous = $this->previousResult($householdId, $mutation->id);
            if (null !== $previous) {
                $connection->commit();

                return $previous;
            }

            // Innerhalb des Kontexts speichern: Das Änderungsprotokoll entsteht beim flush() und soll die Mutation kennen
            $outcome = $this->changeContext->runForMutation($mutation->id, function () use ($householdId, $actorId, $mutation): MutationOutcome {
                $outcome = $this->handle($householdId, $actorId, $mutation);
                $this->entityManager->flush();

                return $outcome;
            });
            $result = self::result($mutation->id, $outcome->status, $outcome->mergedInto);
            $this->remember($householdId, $deviceId, $mutation, $result);
            $this->entityManager->flush();
            $connection->commit();
        } catch (MutationRejected $rejection) {
            $connection->rollBack();
            $this->entityManager->clear();
            $result = self::result($mutation->id, 'rejected', error: $rejection->getMessage());
            $this->remember($householdId, $deviceId, $mutation, $result);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            if (!self::isDataError($exception)) {
                throw $exception; // z. B. Datenbank nicht erreichbar: Push scheitert, das Gerät versucht es später erneut
            }

            // Datenfehler (Wert zu lang, Eindeutigkeit verletzt): Ein neuer Versuch hilft nicht. Ablehnen, damit diese
            // eine Mutation nicht den ganzen Abgleich des Geräts blockiert. Es ist ein Programmfehler, daher ins Log.
            $this->logger->error('Mutation wegen Datenfehler abgelehnt: {type}', ['type' => $mutation->type, 'exception' => $exception]);
            if (!$this->entityManager->isOpen()) {
                $this->doctrine->resetManager(); // Doctrine schließt den EntityManager nach einem Fehler beim Speichern
            }
            $this->entityManager->clear();
            $result = self::result($mutation->id, 'rejected', error: 'Diese Änderung konnte nicht gespeichert werden.');
            $this->remember($householdId, $deviceId, $mutation, $result);
            $this->entityManager->flush();
        } finally {
            $this->entityManager->clear();
        }

        return $result;
    }

    private function handle(Uuid $householdId, Uuid $actorId, IncomingMutation $mutation): MutationOutcome
    {
        if (!$this->handlers->has($mutation->type)) {
            throw new MutationRejected(\sprintf('Unbekannter Mutationstyp "%s".', $mutation->type));
        }

        /** @var MutationHandler<object> $handler */
        $handler = $this->handlers->get($mutation->type);

        try {
            $payload = $this->denormalizer->denormalize($mutation->payload, $handler->payloadClass(), 'json', [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ]);
        } catch (SerializerException|\TypeError $exception) {
            throw new MutationRejected(\sprintf('Ungültige Daten für "%s": %s', $mutation->type, $exception->getMessage()), previous: $exception);
        }

        $violations = $this->validator->validate($payload);
        if (\count($violations) > 0) {
            $violation = $violations->get(0);
            // Die Meldung landet beim Benutzer (Hinweis in der App), der technische Kontext im Log
            $this->logger->notice('Mutation abgelehnt: {type} {path}: {message}', ['type' => $mutation->type, 'path' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()]);
            throw new MutationRejected((string) $violation->getMessage());
        }

        $now = self::toSeconds($this->clock->now());
        $at = self::toSeconds($mutation->at);

        return $handler->handle($payload, new MutationContext(
            householdId: $householdId,
            actorId: $actorId,
            mutationId: $mutation->id,
            at: $at > $now ? $now : $at,
            now: $now,
        ));
    }

    /**
     * Doctrine speichert Zeitstempel in PostgreSQL sekundengenau. Damit Vergleiche im Speicher und nach dem Neuladen
     * gleich ausgehen (z. B. Abhaken vs. Reaktivieren), rechnen wir durchgehend mit ganzen Sekunden.
     */
    private static function toSeconds(\DateTimeImmutable $time): \DateTimeImmutable
    {
        return $time->setTime((int) $time->format('H'), (int) $time->format('i'), (int) $time->format('s'));
    }

    /**
     * Gespeichertes Ergebnis einer schon verarbeiteten Mutation, mit Status "duplicate". Gelesen per DBAL, damit
     * kein veralteter Stand aus der Identity Map kommt.
     *
     * @return array<string, mixed>|null
     */
    private function previousResult(Uuid $householdId, Uuid $mutationId): ?array
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT household_id, result FROM processed_mutation WHERE mutation_id = ?',
            [$mutationId],
            [UuidType::NAME],
        );
        if (false === $row) {
            return null;
        }

        if (!Uuid::fromString((string) $row['household_id'])->equals($householdId)) {
            return self::result($mutationId, 'rejected', error: 'Mutations-ID ist bereits vergeben.');
        }

        /** @var array<string, mixed> $result */
        $result = json_decode((string) $row['result'], true, flags: \JSON_THROW_ON_ERROR);

        return ['status' => 'duplicate', 'original_status' => $result['status'] ?? null] + $result;
    }

    /**
     * SQLSTATE-Klasse 22 (Datenfehler, z. B. Wert zu lang) oder 23 (Integritätsverletzung): Ein neuer Versuch mit
     * denselben Daten scheitert wieder.
     */
    private static function isDataError(\Throwable $exception): bool
    {
        return $exception instanceof DriverException && \in_array(substr((string) $exception->getSQLState(), 0, 2), ['22', '23'], true);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function remember(Uuid $householdId, string $deviceId, IncomingMutation $mutation, array $result): void
    {
        $this->entityManager->persist(new ProcessedMutation(
            $mutation->id,
            $householdId,
            $deviceId,
            mb_substr($mutation->type, 0, 40),
            (string) $result['status'],
            $result,
            $this->clock->now(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function result(Uuid $mutationId, string $status, ?Uuid $mergedInto = null, ?string $error = null): array
    {
        return array_filter([
            'id' => $mutationId->toRfc4122(),
            'status' => $status,
            'merged_into' => $mergedInto?->toRfc4122(),
            'error' => $error,
        ], static fn (?string $value): bool => null !== $value);
    }
}

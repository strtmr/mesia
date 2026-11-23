<?php

declare(strict_types=1);

namespace App\Notification\Domain\Model;

use App\Notification\Domain\PushFrequency;
use App\Notification\Infrastructure\Doctrine\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Web-Push-Abo eines Geräts. Gehört dem Gerät, nicht dem Haushalt: wird nicht synchronisiert.
 *
 * lastSeq merkt sich, bis zu welchem Eintrag im Änderungsprotokoll das Gerät schon informiert ist.
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscription')]
#[ORM\UniqueConstraint(name: 'push_subscription_endpoint_unique', columns: ['endpoint'])]
#[ORM\Index(name: 'push_subscription_household_idx', columns: ['household_id'])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $userId;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $householdId;

    /** Adresse beim Push-Dienst des Browsers (Apple, Google, Mozilla) */
    #[ORM\Column(length: 1000)]
    private string $endpoint;

    #[ORM\Column(length: 200)]
    private string $publicKey;

    #[ORM\Column(length: 100)]
    private string $authToken;

    #[ORM\Column(length: 20, enumType: PushFrequency::class)]
    private PushFrequency $frequency;

    /** DBAL liefert bigint als int (oder string, falls zu groß für PHP) */
    #[ORM\Column(type: Types::BIGINT)]
    private int|string $lastSeq;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSentAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Uuid $userId, Uuid $householdId, string $endpoint, string $publicKey, string $authToken, PushFrequency $frequency, int $lastSeq, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->endpoint = $endpoint;
        $this->createdAt = $now;
        $this->householdId = $householdId;
        $this->lastSeq = $lastSeq;
        $this->update($userId, $householdId, $publicKey, $authToken, $frequency, $lastSeq);
    }

    /**
     * Erneut abonniert (z. B. Häufigkeit geändert oder anderes Konto auf dem Gerät).
     */
    public function update(Uuid $userId, Uuid $householdId, string $publicKey, string $authToken, PushFrequency $frequency, int $lastSeq): void
    {
        // Anderer Haushalt: ab jetzt zählen, nicht rückwirkend benachrichtigen
        if (!$this->householdId->equals($householdId)) {
            $this->lastSeq = $lastSeq;
        }
        $this->userId = $userId;
        $this->householdId = $householdId;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->frequency = $frequency;
    }

    /**
     * Darf jetzt eine Nachricht kommen? Bei "alle 15 Minuten" erst, wenn die letzte lange genug her ist.
     */
    public function isDue(\DateTimeImmutable $now): bool
    {
        $interval = $this->frequency->minimumInterval();

        return null === $interval || null === $this->lastSentAt || $this->lastSentAt->add($interval) <= $now;
    }

    public function markSent(int $upToSeq, \DateTimeImmutable $now): void
    {
        $this->lastSeq = $upToSeq;
        $this->lastSentAt = $now;
    }

    /** Nichts Neues: trotzdem weiterrücken, damit alte Einträge nicht immer wieder geprüft werden */
    public function skipTo(int $seq): void
    {
        if ($seq > $this->getLastSeq()) {
            $this->lastSeq = $seq;
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getHouseholdId(): Uuid
    {
        return $this->householdId;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getFrequency(): PushFrequency
    {
        return $this->frequency;
    }

    public function getLastSeq(): int
    {
        return (int) $this->lastSeq;
    }
}

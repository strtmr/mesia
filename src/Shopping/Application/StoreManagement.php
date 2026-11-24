<?php

declare(strict_types=1);

namespace App\Shopping\Application;

use App\Shared\Infrastructure\Persistence\HouseholdWriteLock;
use App\Shopping\Domain\Model\Store;
use App\Shopping\Infrastructure\Doctrine\StoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Läden anlegen, umbenennen und löschen („Mehr → Läden“). Wie bei den Kategorien: Transaktion mit Haushalts-Sperre,
 * die Änderungen erreichen die Geräte über das Änderungsprotokoll.
 *
 * Gelöschte Läden bleiben in den Einträgen stehen, die Liste blendet sie aus. So geht offline nichts verloren.
 */
final readonly class StoreManagement
{
    public function __construct(
        private StoreRepository $stores,
        private EntityManagerInterface $entityManager,
        private HouseholdWriteLock $writeLock,
        private ClockInterface $clock,
    ) {
    }

    public function create(Uuid $householdId, string $name): Uuid
    {
        return $this->transactional($householdId, function (\DateTimeImmutable $now) use ($householdId, $name): Uuid {
            $this->assertNameIsFree($householdId, $name);
            $store = new Store(Uuid::v7(), $householdId, $name, $now);
            $this->entityManager->persist($store);

            return $store->getId();
        });
    }

    public function rename(Uuid $householdId, Uuid $storeId, string $name): void
    {
        $this->transactional($householdId, function (\DateTimeImmutable $now) use ($householdId, $storeId, $name): void {
            $store = $this->get($householdId, $storeId);
            $this->assertNameIsFree($householdId, $name, $store);
            $store->rename($name, $now);
        });
    }

    public function delete(Uuid $householdId, Uuid $storeId): void
    {
        $this->transactional($householdId, function (\DateTimeImmutable $now) use ($householdId, $storeId): void {
            $this->get($householdId, $storeId)->softDelete($now);
        });
    }

    public function get(Uuid $householdId, Uuid $storeId): Store
    {
        return $this->stores->findActive($householdId, $storeId) ?? throw new StoreRuleViolation('Laden nicht gefunden.');
    }

    /**
     * @template T
     *
     * @param \Closure(\DateTimeImmutable): T $operation
     *
     * @return T
     */
    private function transactional(Uuid $householdId, \Closure $operation): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($householdId, $operation): mixed {
            $this->writeLock->acquire($householdId);

            return $operation($this->clock->now());
        });
    }

    private function assertNameIsFree(Uuid $householdId, string $name, ?Store $self = null): void
    {
        $wanted = mb_strtolower(trim($name));
        foreach ($this->stores->findAllActive($householdId) as $store) {
            if ($store !== $self && mb_strtolower($store->getName()) === $wanted) {
                throw new StoreRuleViolation(\sprintf('Es gibt schon einen Laden „%s“.', $store->getName()));
            }
        }
    }
}

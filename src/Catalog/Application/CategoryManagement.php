<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Event\CategoryDeleted;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Shared\Domain\SortKey;
use App\Shared\Infrastructure\Persistence\HouseholdWriteLock;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Kategorien anlegen, ändern, sortieren und löschen (Seite „Mehr → Kategorien“).
 *
 * Jede Änderung läuft in einer Transaktion mit Haushalts-Sperre und landet über die Entities im Änderungsprotokoll.
 * So erscheinen sie auf allen Geräten, die Einkaufsliste selbst bleibt offline-fähig.
 */
final readonly class CategoryManagement
{
    public function __construct(
        private CategoryRepository $categories,
        private ArticleRepository $articles,
        private EntityManagerInterface $entityManager,
        private HouseholdWriteLock $writeLock,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Neue Kategorie, eingereiht vor „Sonstiges“.
     */
    public function create(Uuid $householdId, string $name, string $icon): Uuid
    {
        return $this->transactional($householdId, function (\DateTimeImmutable $now) use ($householdId, $name, $icon): Uuid {
            $this->assertNameIsFree($householdId, $name);
            $category = new Category(Uuid::v7(), $householdId, $name, $icon, 'a0', $now);
            $this->entityManager->persist($category);

            $ordered = $this->categories->findAllActiveOrdered($householdId);
            $fallbackIndex = array_search(true, array_map(static fn (Category $c): bool => $c->isFallback(), $ordered), true);
            array_splice($ordered, false === $fallbackIndex ? \count($ordered) : $fallbackIndex, 0, [$category]);
            $this->renumber($ordered, $now);

            return $category->getId();
        });
    }

    public function update(Uuid $householdId, Uuid $categoryId, string $name, string $icon): void
    {
        $this->transactional($householdId, function (\DateTimeImmutable $now) use ($householdId, $categoryId, $name, $icon): void {
            $category = $this->get($householdId, $categoryId);
            $this->assertNameIsFree($householdId, $name, $category);
            $category->rename($name, $icon, $now);
        });
    }

    /**
     * Neue Reihenfolge. $orderedIds muss genau die aktiven Kategorien des Haushalts enthalten.
     *
     * @param list<Uuid> $orderedIds
     */
    public function reorder(Uuid $householdId, array $orderedIds): void
    {
        $this->transactional($householdId, function (\DateTimeImmutable $now) use ($householdId, $orderedIds): void {
            $byId = [];
            foreach ($this->categories->findAllActive($householdId) as $category) {
                $byId[$category->getId()->toRfc4122()] = $category;
            }

            $ordered = [];
            foreach ($orderedIds as $id) {
                $ordered[] = $byId[$id->toRfc4122()] ?? throw new CategoryRuleViolation('Die Kategorien haben sich inzwischen geändert. Bitte die Seite neu laden.');
                unset($byId[$id->toRfc4122()]);
            }
            if ([] !== $byId) {
                throw new CategoryRuleViolation('Die Kategorien haben sich inzwischen geändert. Bitte die Seite neu laden.');
            }

            $this->renumber($ordered, $now);
        });
    }

    /**
     * Löschen: Artikel und Einträge wandern nach „Sonstiges“. „Sonstiges“ selbst bleibt immer erhalten.
     */
    public function delete(Uuid $householdId, Uuid $categoryId): void
    {
        $this->transactional($householdId, function (\DateTimeImmutable $now) use ($householdId, $categoryId): void {
            $category = $this->get($householdId, $categoryId);
            if ($category->isFallback()) {
                throw new CategoryRuleViolation(\sprintf('„%s“ nimmt alle übrigen Artikel auf und kann nicht gelöscht werden.', $category->getName()));
            }
            $fallback = $this->categories->findFallback($householdId) ?? throw new \LogicException('Haushalt ohne Fallback-Kategorie.');

            $this->articles->reassignCategory($householdId, $categoryId, $fallback->getId(), $now);
            $this->eventDispatcher->dispatch(new CategoryDeleted($householdId, $categoryId, $fallback->getId(), $now));
            $category->softDelete($now);
        });
    }

    public function get(Uuid $householdId, Uuid $categoryId): Category
    {
        return $this->categories->findActive($householdId, $categoryId)
            ?? throw new CategoryNotFound('Kategorie nicht gefunden.');
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

    private function assertNameIsFree(Uuid $householdId, string $name, ?Category $self = null): void
    {
        $wanted = mb_strtolower(trim($name));
        foreach ($this->categories->findAllActive($householdId) as $category) {
            if ($category !== $self && mb_strtolower($category->getName()) === $wanted) {
                throw new CategoryRuleViolation(\sprintf('Es gibt schon eine Kategorie „%s“.', $category->getName()));
            }
        }
    }

    /**
     * Vergibt fortlaufende Sortierschlüssel. Nur Kategorien mit geändertem Schlüssel werden gespeichert.
     *
     * @param list<Category> $ordered
     */
    private function renumber(array $ordered, \DateTimeImmutable $now): void
    {
        foreach (SortKey::sequence(\count($ordered)) as $index => $sortKey) {
            $ordered[$index]->moveTo($sortKey, $now);
        }
    }
}

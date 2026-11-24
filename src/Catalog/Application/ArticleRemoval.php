<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Event\ArticleDeleted;
use App\Catalog\Infrastructure\Doctrine\ArticleAliasRepository;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Shared\Infrastructure\Persistence\HouseholdWriteLock;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Artikel aus dem Katalog eines Haushalts löschen (Admin-Bereich: Tippfehler, Testeinträge).
 *
 * Weich gelöscht samt Aliasen, damit die Geräte es über das Änderungsprotokoll erfahren. {@see ArticleDeleted}
 * lässt andere Module aufräumen. Tippt jemand den Namen später wieder ein, entsteht einfach ein neuer Artikel.
 */
final readonly class ArticleRemoval
{
    public function __construct(
        private ArticleRepository $articles,
        private ArticleAliasRepository $aliases,
        private EntityManagerInterface $entityManager,
        private HouseholdWriteLock $writeLock,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return bool false, wenn es den Artikel (im Haushalt) nicht oder nicht mehr gibt
     */
    public function delete(Uuid $householdId, Uuid $articleId): bool
    {
        return $this->entityManager->wrapInTransaction(function () use ($householdId, $articleId): bool {
            $this->writeLock->acquire($householdId);
            $article = $this->articles->findActive($householdId, $articleId);
            if (null === $article) {
                return false;
            }

            $now = $this->clock->now();
            foreach ($this->aliases->findActiveByArticle($householdId, $articleId) as $alias) {
                $alias->softDelete($now);
            }
            $article->softDelete($now);
            $this->eventDispatcher->dispatch(new ArticleDeleted($householdId, $articleId, $now));

            return true;
        });
    }
}

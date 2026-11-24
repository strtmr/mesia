<?php

declare(strict_types=1);

namespace App\Shopping\Infrastructure\Doctrine;

use App\Shopping\Domain\Model\ShoppingListItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ShoppingListItem>
 */
final class ShoppingListItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingListItem::class);
    }

    public function findActive(Uuid $householdId, Uuid $itemId): ?ShoppingListItem
    {
        return $this->findOneBy(['id' => $itemId, 'householdId' => $householdId, 'deletedAt' => null]);
    }

    /**
     * Offener oder erledigter (nicht gelöschter) Eintrag zu einem Artikel. Es gibt höchstens einen pro Liste.
     */
    public function findActiveByArticle(Uuid $listId, Uuid $articleId): ?ShoppingListItem
    {
        return $this->findOneBy(['listId' => $listId, 'articleId' => $articleId, 'deletedAt' => null]);
    }

    /**
     * @return list<ShoppingListItem>
     */
    public function findAllActive(Uuid $householdId): array
    {
        return $this->findBy(['householdId' => $householdId, 'deletedAt' => null]);
    }

    /**
     * @return list<ShoppingListItem>
     */
    public function findByCategory(Uuid $householdId, Uuid $categoryId): array
    {
        return $this->findBy(['householdId' => $householdId, 'categoryId' => $categoryId, 'deletedAt' => null]);
    }

    /**
     * @return list<ShoppingListItem>
     */
    public function findByArticle(Uuid $householdId, Uuid $articleId): array
    {
        return $this->findBy(['householdId' => $householdId, 'articleId' => $articleId, 'deletedAt' => null]);
    }
}

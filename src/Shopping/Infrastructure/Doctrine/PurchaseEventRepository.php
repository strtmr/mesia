<?php

declare(strict_types=1);

namespace App\Shopping\Infrastructure\Doctrine;

use App\Shopping\Domain\Model\PurchaseEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PurchaseEvent>
 */
final class PurchaseEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseEvent::class);
    }

    public function findLatestForItem(Uuid $itemId): ?PurchaseEvent
    {
        return $this->findOneBy(['itemId' => $itemId], ['purchasedAt' => 'DESC']);
    }

    /**
     * @return list<PurchaseEvent>
     */
    public function findForItem(Uuid $itemId): array
    {
        return $this->findBy(['itemId' => $itemId], ['purchasedAt' => 'ASC']);
    }
}

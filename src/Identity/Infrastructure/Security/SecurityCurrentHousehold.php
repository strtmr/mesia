<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use App\Shared\Application\CurrentHousehold;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

#[AsAlias(CurrentHousehold::class)]
final readonly class SecurityCurrentHousehold implements CurrentHousehold
{
    public function __construct(private Security $security)
    {
    }

    public function id(): Uuid
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Kein angemeldeter Benutzer.');
        }

        return $user->getHousehold()->getId();
    }
}

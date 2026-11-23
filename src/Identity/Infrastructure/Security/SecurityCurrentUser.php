<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use App\Shared\Application\CurrentUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

#[AsAlias(CurrentUser::class)]
final readonly class SecurityCurrentUser implements CurrentUser
{
    public function __construct(private Security $security)
    {
    }

    public function id(): Uuid
    {
        return $this->user()->getId();
    }

    public function householdId(): Uuid
    {
        return $this->user()->getHousehold()->getId();
    }

    private function user(): User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : throw new \LogicException('Kein angemeldeter Benutzer.');
    }
}

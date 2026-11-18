<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use App\Shared\Application\ChangeLog\ChangeContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Trägt den angemeldeten Benutzer als "Actor" in den ChangeContext ein, damit das Änderungsprotokoll weiß,
 * wer eine Änderung gemacht hat.
 *
 * Priorität 0: läuft nach der Firewall (Priorität 8), die das Token setzt.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class SetChangeActorListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ChangeContext $changeContext,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        $this->changeContext->setActorId($user instanceof User ? $user->getId() : null);
    }
}

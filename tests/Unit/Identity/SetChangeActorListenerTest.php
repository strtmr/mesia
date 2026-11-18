<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Domain\Model\Household;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Security\SetChangeActorListener;
use App\Shared\Application\ChangeLog\ChangeContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SetChangeActorListenerTest extends TestCase
{
    public function testLoggedInUserBecomesActor(): void
    {
        $user = new User(new Household('Familie Test', new \DateTimeImmutable()), 'marco@example.com', 'Marco', new \DateTimeImmutable());
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $context = new ChangeContext();

        (new SetChangeActorListener($tokenStorage, $context))($this->event(HttpKernelInterface::MAIN_REQUEST));

        self::assertSame($user->getId(), $context->actorId());
    }

    public function testAnonymousRequestHasNoActor(): void
    {
        $context = new ChangeContext();

        (new SetChangeActorListener(new TokenStorage(), $context))($this->event(HttpKernelInterface::MAIN_REQUEST));

        self::assertNull($context->actorId());
    }

    private function event(int $requestType): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $requestType);
    }
}

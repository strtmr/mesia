<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Push;

use App\Notification\Application\PushMessage;
use App\Notification\Application\PushResult;
use App\Notification\Application\PushSender;
use App\Notification\Domain\Model\PushSubscription;

/**
 * Test-Doppel (in der Testumgebung statt WebPushSender): merkt sich die Nachrichten, statt sie zu verschicken.
 */
final class InMemoryPushSender implements PushSender
{
    /** @var list<array{endpoint: string, message: PushMessage}> */
    public array $sent = [];

    /** Ergebnis für die nächsten Sendungen, z. B. Gone, um ein abgelaufenes Abo nachzustellen */
    public PushResult $nextResult = PushResult::Sent;

    public function isConfigured(): bool
    {
        return true;
    }

    public function publicKey(): string
    {
        return 'BTestPublicKey';
    }

    public function send(PushSubscription $subscription, PushMessage $message): PushResult
    {
        if (PushResult::Sent === $this->nextResult) {
            $this->sent[] = ['endpoint' => $subscription->getEndpoint(), 'message' => $message];
        }

        return $this->nextResult;
    }
}

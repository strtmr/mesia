<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Push;

use App\Notification\Application\PushMessage;
use App\Notification\Application\PushResult;
use App\Notification\Application\PushSender;
use App\Notification\Domain\Model\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Versand über Web Push (minishlink/web-push) mit VAPID-Schlüsseln aus der Umgebung. Ohne Schlüssel ist Push aus.
 */
#[AsAlias(PushSender::class)]
final class WebPushSender implements PushSender
{
    private ?WebPush $webPush = null;

    public function __construct(
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private readonly string $vapidPublicKey,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')]
        private readonly string $vapidPrivateKey,
        #[Autowire('%env(VAPID_SUBJECT)%')]
        private readonly string $vapidSubject,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->vapidPublicKey && '' !== $this->vapidPrivateKey;
    }

    public function publicKey(): string
    {
        return $this->vapidPublicKey;
    }

    public function send(PushSubscription $subscription, PushMessage $message): PushResult
    {
        $report = $this->webPush()->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->getEndpoint(),
                'publicKey' => $subscription->getPublicKey(),
                'authToken' => $subscription->getAuthToken(),
                'contentEncoding' => 'aes128gcm',
            ]),
            json_encode($message->toArray(), \JSON_THROW_ON_ERROR),
            // Nach einer Stunde ist "XY braucht Milch" nicht mehr interessant; "topic" ersetzt Wartendes beim Push-Dienst
            ['TTL' => 3600, 'urgency' => 'normal', 'topic' => $message->tag],
        );

        if ($report->isSuccess()) {
            return PushResult::Sent;
        }
        if ($report->isSubscriptionExpired()) {
            return PushResult::Gone;
        }
        $this->logger->warning('Web Push fehlgeschlagen: {reason}', ['reason' => $report->getReason()]);

        return PushResult::Failed;
    }

    private function webPush(): WebPush
    {
        return $this->webPush ??= new WebPush(
            ['VAPID' => ['subject' => $this->vapidSubject, 'publicKey' => $this->vapidPublicKey, 'privateKey' => $this->vapidPrivateKey]],
            [],
            $this->client,
            $this->requestFactory,
            $this->streamFactory,
            logger: $this->logger,
        );
    }
}

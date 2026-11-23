<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Infrastructure\Doctrine\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Verschickt fällige Benachrichtigungen (Cron, app:push:send).
 *
 * Pro Gerät: Hat seit der letzten Nachricht jemand anderes etwas auf die Liste gesetzt und ist das Gerät laut seiner
 * Häufigkeit wieder dran, gibt es eine Sammel-Nachricht. Danach gilt alles bis dahin als gemeldet.
 */
final readonly class PushDispatcher
{
    public function __construct(
        private PushSubscriptionRepository $subscriptions,
        private AddedItemsFeed $feed,
        private NotificationText $text,
        private PushSender $sender,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{sent: int, removed: int, failed: int}
     */
    public function dispatch(): array
    {
        $stats = ['sent' => 0, 'removed' => 0, 'failed' => 0];
        if (!$this->sender->isConfigured()) {
            return $stats;
        }
        $now = $this->clock->now();

        foreach ($this->subscriptions->findAll() as $subscription) {
            if (!$subscription->isDue($now)) {
                continue; // gesammelt wird weiter, lastSeq bleibt stehen
            }

            $items = $this->feed->addedByOthers($subscription->getHouseholdId(), $subscription->getLastSeq(), $subscription->getUserId());
            if ([] === $items) {
                $subscription->skipTo($this->feed->latestSeq($subscription->getHouseholdId()));
                continue;
            }

            $result = $this->sender->send($subscription, $this->text->compose($items));
            if (PushResult::Sent === $result) {
                $subscription->markSent($items[\count($items) - 1]->seq, $now);
                ++$stats['sent'];
            } elseif (PushResult::Gone === $result) {
                $this->entityManager->remove($subscription);
                ++$stats['removed'];
            } else {
                // lastSeq bleibt stehen: Der nächste Lauf versucht es erneut
                $this->logger->warning('Push-Nachricht konnte nicht zugestellt werden', ['subscription' => $subscription->getId()->toRfc4122()]);
                ++$stats['failed'];
            }
        }

        $this->entityManager->flush();

        return $stats;
    }
}

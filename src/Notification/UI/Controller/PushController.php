<?php

declare(strict_types=1);

namespace App\Notification\UI\Controller;

use App\Notification\Application\AddedItemsFeed;
use App\Notification\Application\PushSender;
use App\Notification\Domain\Model\PushSubscription;
use App\Notification\Infrastructure\Doctrine\PushSubscriptionRepository;
use App\Notification\UI\Http\EndpointRequest;
use App\Notification\UI\Http\SubscribeRequest;
use App\Shared\Application\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Push-Abos verwalten ("Mehr → Mitteilungen", controllers/push_settings_controller.js).
 * Schreibende Requests brauchen wie die übrige API den Header X-Requested-With (ApiRequestGuard).
 */
#[Route('/api/push', name: 'api_push_')]
final class PushController extends AbstractController
{
    public function __construct(
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly PushSender $sender,
        private readonly CurrentUser $user,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Ist Push eingerichtet, welcher Schlüssel, und kennt der Server dieses Gerät schon?
     */
    #[Route('', name: 'status', methods: ['GET'])]
    public function status(#[MapQueryString] ?EndpointRequest $query = null): JsonResponse
    {
        $subscription = null === $query ? null : $this->subscriptions->findByEndpoint($query->endpoint);
        $own = null !== $subscription && $subscription->getUserId()->equals($this->user->id());

        return $this->json([
            'enabled' => $this->sender->isConfigured(),
            'public_key' => $this->sender->publicKey(),
            'subscribed' => $own,
            'frequency' => $own ? $subscription->getFrequency()->value : null,
        ]);
    }

    #[Route('/subscription', name: 'subscribe', methods: ['POST'])]
    public function subscribe(#[MapRequestPayload] SubscribeRequest $request, AddedItemsFeed $feed, ClockInterface $clock): Response
    {
        $householdId = $this->user->householdId();
        $latest = $feed->latestSeq($householdId);
        $subscription = $this->subscriptions->findByEndpoint($request->endpoint);

        if (null === $subscription) {
            $this->entityManager->persist(new PushSubscription($this->user->id(), $householdId, $request->endpoint, $request->p256dh, $request->auth, $request->frequency, $latest, $clock->now()));
        } else {
            $subscription->update($this->user->id(), $householdId, $request->p256dh, $request->auth, $request->frequency, $latest);
        }
        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/subscription', name: 'unsubscribe', methods: ['DELETE'])]
    public function unsubscribe(#[MapRequestPayload] EndpointRequest $request): Response
    {
        $subscription = $this->subscriptions->findByEndpoint($request->endpoint);
        if (null !== $subscription && $subscription->getUserId()->equals($this->user->id())) {
            $this->entityManager->remove($subscription);
            $this->entityManager->flush();
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}

<?php

declare(strict_types=1);

namespace App\Sync\UI\Controller;

use App\Identity\Domain\Model\User;
use App\Sync\Application\MutationDispatcher;
use App\Sync\Application\SnapshotBuilder;
use App\Sync\Infrastructure\ChangeFeed;
use App\Sync\UI\Http\PushRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Sync-Protokoll, siehe docs/entwicklung.md und docs/spezifikation-phase-0-1.md, Kapitel 4.
 */
#[Route('/api/sync', name: 'api_sync_')]
final class SyncController extends AbstractController
{
    #[Route('/snapshot', name: 'snapshot', methods: ['GET'])]
    public function snapshot(#[CurrentUser] User $user, SnapshotBuilder $snapshots): JsonResponse
    {
        return $this->json($snapshots->build($user->getHousehold()->getId()));
    }

    #[Route('/push', name: 'push', methods: ['POST'])]
    public function push(#[CurrentUser] User $user, #[MapRequestPayload] PushRequest $request, MutationDispatcher $dispatcher): JsonResponse
    {
        $results = $dispatcher->dispatch($user->getHousehold()->getId(), $user->getId(), $request->deviceId, $request->mutations);

        return $this->json(['results' => $results]);
    }

    #[Route('/pull', name: 'pull', methods: ['GET'])]
    public function pull(
        #[CurrentUser] User $user,
        ChangeFeed $changeFeed,
        #[MapQueryParameter(options: ['min_range' => 0])] int $since = 0,
        #[MapQueryParameter(options: ['min_range' => 1, 'max_range' => 500])] int $limit = 500,
    ): JsonResponse {
        return $this->json($changeFeed->since($user->getHousehold()->getId(), $since, $limit));
    }
}

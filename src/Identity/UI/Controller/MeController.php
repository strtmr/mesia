<?php

declare(strict_types=1);

namespace App\Identity\UI\Controller;

use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MeController extends AbstractController
{
    /**
     * Wer bin ich? Clients prüfen damit nach dem Start, ob die Sitzung noch gilt (401 = neu anmelden).
     * Die Mitglieder des Haushalts braucht die Liste für „Hinzugefügt von …“ und „Erledigt von …“.
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user, UserRepository $users): JsonResponse
    {
        return $this->json([
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'display_name' => $user->getDisplayName(),
            'household' => [
                'id' => $user->getHousehold()->getId()->toRfc4122(),
                'name' => $user->getHousehold()->getName(),
                'members' => array_map(static fn (User $member): array => [
                    'id' => $member->getId()->toRfc4122(),
                    'display_name' => $member->getDisplayName(),
                ], $users->findByHousehold($user->getHousehold())),
            ],
        ]);
    }
}

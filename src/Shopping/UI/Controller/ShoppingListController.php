<?php

declare(strict_types=1);

namespace App\Shopping\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShoppingListController extends AbstractController
{
    /**
     * Platzhalter. In Phase 1 wird diese Seite zur benutzerunabhängigen App-Hülle der Offline-Einkaufsliste.
     */
    #[Route('/einkauf', name: 'shopping_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('shopping/list.html.twig');
    }
}

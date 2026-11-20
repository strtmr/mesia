<?php

declare(strict_types=1);

namespace App\Shared\UI\Controller;

use App\Shared\Application\ReleaseNotes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    /**
     * Startseite: In Phase 1 ist das die Einkaufsliste. Später evtl. ein Dashboard.
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('shopping_list');
    }

    #[Route('/mehr', name: 'app_more', methods: ['GET'])]
    public function more(): Response
    {
        return $this->render('app/more.html.twig');
    }

    #[Route('/neuigkeiten', name: 'app_release_notes', methods: ['GET'])]
    public function releaseNotes(ReleaseNotes $releaseNotes): Response
    {
        return $this->render('app/release_notes.html.twig', ['notes' => $releaseNotes->all()]);
    }
}

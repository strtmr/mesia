<?php

declare(strict_types=1);

namespace App\Admin\UI\Controller;

use App\Admin\Application\AdminStatistics;
use App\Admin\UI\Form\PersonData;
use App\Admin\UI\Form\PersonType;
use App\Admin\UI\Http\PurchaseFilterRequest;
use App\Identity\Application\UserRegistration;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\HouseholdRepository;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Admin-Bereich: Überblick über alle Haushalte, Auswertungen für den Startkatalog, Haushalte und Personen anlegen.
 * Nur mit ROLE_ADMIN (app:user:admin). Gehört nicht zur Benutzer-App: keine Version, kein Eintrag in den Neuigkeiten.
 */
#[Route('/admin', name: 'admin')]
#[IsGranted(User::ROLE_ADMIN)]
final class AdminController extends AbstractController
{
    /** Zeitraum für „Käufe“ in Überblick und Haushaltsliste */
    private const string RECENT = '-30 days';

    public function __construct(
        private readonly AdminStatistics $statistics,
        private readonly UserRegistration $registration,
        private readonly HouseholdRepository $households,
        private readonly UserRepository $users,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function dashboard(): Response
    {
        $since = $this->clock->now()->modify(self::RECENT);

        return $this->render('admin/dashboard.html.twig', [
            'totals' => $this->statistics->totals($since),
            'households' => $this->statistics->households($since),
        ]);
    }

    #[Route('/artikel', name: '_user_articles', methods: ['GET'])]
    public function userArticles(): Response
    {
        return $this->render('admin/user_articles.html.twig', [
            'articles' => $this->statistics->userArticles(100),
        ]);
    }

    #[Route('/einkaeufe', name: '_purchases', methods: ['GET'])]
    public function purchases(#[MapQueryString] PurchaseFilterRequest $filter = new PurchaseFilterRequest()): Response
    {
        $since = 'alle' === $filter->zeitraum ? null : $this->clock->now()->modify(\sprintf('-%d days', (int) $filter->zeitraum));
        $householdId = null === $filter->haushalt || '' === $filter->haushalt ? null : Uuid::fromString($filter->haushalt);

        return $this->render('admin/purchases.html.twig', [
            'articles' => $this->statistics->topPurchases($since, $householdId, 50),
            'filter' => $filter,
            'periods' => PurchaseFilterRequest::PERIODS,
            'households' => $this->households->findAllOrderedByName(),
        ]);
    }

    #[Route('/haushalte/neu', name: '_household_new', methods: ['GET', 'POST'])]
    public function newHousehold(Request $request): Response
    {
        $form = $this->createForm(PersonType::class, new PersonData(), ['with_household' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->emailIsFree($form)) {
            /** @var PersonData $data */
            $data = $form->getData();
            $user = $this->registration->createHouseholdWithUser((string) $data->householdName, (string) $data->email, (string) $data->displayName, (string) $data->password);
            $this->addFlash('success', \sprintf('Haushalt „%s“ mit %s angelegt.', $user->getHousehold()->getName(), $user->getDisplayName()));

            return $this->redirectToRoute('admin', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/person_new.html.twig', ['form' => $form, 'household' => null], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/haushalte/{id}/person', name: '_member_new', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET', 'POST'])]
    public function newMember(Request $request, string $id): Response
    {
        $household = $this->households->find(Uuid::fromString($id)) ?? throw $this->createNotFoundException();
        $form = $this->createForm(PersonType::class, new PersonData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->emailIsFree($form)) {
            /** @var PersonData $data */
            $data = $form->getData();
            $user = $this->registration->createUser($household, (string) $data->email, (string) $data->displayName, (string) $data->password);
            $this->addFlash('success', \sprintf('%s zum Haushalt „%s“ hinzugefügt.', $user->getDisplayName(), $household->getName()));

            return $this->redirectToRoute('admin', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/person_new.html.twig', ['form' => $form, 'household' => $household], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    /**
     * @param FormInterface<PersonData> $form
     */
    private function emailIsFree(FormInterface $form): bool
    {
        if (null === $this->users->findOneByEmail((string) $form->getData()->email)) {
            return true;
        }
        $form->get('email')->addError(new FormError('Diese E-Mail-Adresse hat schon ein Konto.'));

        return false;
    }
}

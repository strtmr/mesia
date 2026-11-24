<?php

declare(strict_types=1);

namespace App\Shopping\UI\Controller;

use App\Shared\Application\CurrentHousehold;
use App\Shopping\Application\StoreManagement;
use App\Shopping\Application\StoreRuleViolation;
use App\Shopping\Domain\Model\Store;
use App\Shopping\Infrastructure\Doctrine\StoreRepository;
use App\Shopping\UI\Form\StoreData;
use App\Shopping\UI\Form\StoreType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * „Mehr → Läden“: Läden, denen man Einträge der Einkaufsliste zuordnen kann. Normale Seiten mit Verbindung.
 */
#[Route('/mehr/laeden', name: 'shopping_stores')]
final class StoreController extends AbstractController
{
    public const string DELETE_TOKEN = 'delete-store';
    private const string ID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(
        private readonly StoreManagement $management,
        private readonly StoreRepository $stores,
        private readonly CurrentHousehold $household,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('shopping/stores/index.html.twig', ['stores' => $this->stores->findAllActive($this->household->id())]);
    }

    #[Route('/neu', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(StoreType::class, new StoreData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->management->create($this->household->id(), (string) $form->getData()->name);

                return $this->redirectToRoute('shopping_stores', status: Response::HTTP_SEE_OTHER);
            } catch (StoreRuleViolation $violation) {
                $form->get('name')->addError(new FormError($violation->getMessage()));
            }
        }

        return $this->renderForm('shopping/stores/new.html.twig', $form);
    }

    #[Route('/{id}', name: '_edit', requirements: ['id' => self::ID], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $store = $this->find($id);
        $data = new StoreData();
        $data->name = $store->getName();
        $form = $this->createForm(StoreType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->management->rename($this->household->id(), $store->getId(), (string) $data->name);

                return $this->redirectToRoute('shopping_stores', status: Response::HTTP_SEE_OTHER);
            } catch (StoreRuleViolation $violation) {
                $form->get('name')->addError(new FormError($violation->getMessage()));
            }
        }

        return $this->renderForm('shopping/stores/edit.html.twig', $form, ['store' => $store, 'delete_token' => self::DELETE_TOKEN]);
    }

    #[Route('/{id}/loeschen', name: '_delete', requirements: ['id' => self::ID], methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        $store = $this->find($id);
        if (!$this->isCsrfTokenValid(self::DELETE_TOKEN, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $this->management->delete($this->household->id(), $store->getId());

        return $this->redirectToRoute('shopping_stores', status: Response::HTTP_SEE_OTHER);
    }

    private function find(string $id): Store
    {
        try {
            return $this->management->get($this->household->id(), Uuid::fromString($id));
        } catch (StoreRuleViolation) {
            throw $this->createNotFoundException();
        }
    }

    /**
     * @param FormInterface<StoreData> $form
     * @param array<string, mixed>     $parameters
     */
    private function renderForm(string $template, FormInterface $form, array $parameters = []): Response
    {
        $status = $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render($template, ['form' => $form] + $parameters, new Response(status: $status));
    }
}

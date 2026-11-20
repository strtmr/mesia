<?php

declare(strict_types=1);

namespace App\Catalog\UI\Controller;

use App\Catalog\Application\CategoryManagement;
use App\Catalog\Application\CategoryNotFound;
use App\Catalog\Application\CategoryRuleViolation;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Catalog\UI\Form\CategoryData;
use App\Catalog\UI\Form\CategoryType;
use App\Catalog\UI\Http\ReorderCategoriesRequest;
use App\Shared\Application\CurrentHousehold;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * „Mehr → Kategorien“: normale Seiten mit Verbindung. Die Einkaufsliste übernimmt die Änderungen beim nächsten Sync.
 */
#[Route('/mehr/kategorien', name: 'catalog_categories')]
final class CategoryController extends AbstractController
{
    public const string REORDER_TOKEN = 'reorder-categories';
    public const string DELETE_TOKEN = 'delete-category';

    public function __construct(
        private readonly CategoryManagement $management,
        private readonly CategoryRepository $categories,
        private readonly CurrentHousehold $household,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('catalog/categories.html.twig', [
            'categories' => $this->categories->findAllActiveOrdered($this->household->id()),
            'reorder_token' => self::REORDER_TOKEN,
        ]);
    }

    #[Route('/neu', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(CategoryType::class, new CategoryData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CategoryData $data */
            $data = $form->getData();
            try {
                $this->management->create($this->household->id(), (string) $data->name, (string) $data->icon);

                return $this->redirectToRoute('catalog_categories', status: Response::HTTP_SEE_OTHER);
            } catch (CategoryRuleViolation $violation) {
                $form->get('name')->addError(new FormError($violation->getMessage()));
            }
        }

        return $this->renderForm('catalog/category_new.html.twig', $form);
    }

    #[Route('/{id}', name: '_edit', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $category = $this->find($id);
        $data = new CategoryData();
        $data->name = $category->getName();
        $data->icon = $category->getIcon();

        $form = $this->createForm(CategoryType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->management->update($this->household->id(), $category->getId(), (string) $data->name, (string) $data->icon);

                return $this->redirectToRoute('catalog_categories', status: Response::HTTP_SEE_OTHER);
            } catch (CategoryRuleViolation $violation) {
                $form->get('name')->addError(new FormError($violation->getMessage()));
            }
        }

        return $this->renderForm('catalog/category_edit.html.twig', $form, [
            'category' => $category,
            'delete_token' => self::DELETE_TOKEN,
        ]);
    }

    #[Route('/{id}/loeschen', name: '_delete', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        $category = $this->find($id);
        if (!$this->isCsrfTokenValid(self::DELETE_TOKEN, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }

        try {
            $this->management->delete($this->household->id(), $category->getId());
        } catch (CategoryRuleViolation $violation) {
            $this->addFlash('error', $violation->getMessage());
        }

        return $this->redirectToRoute('catalog_categories', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/reihenfolge', name: '_reorder', methods: ['POST'])]
    public function reorder(#[MapRequestPayload] ReorderCategoriesRequest $request): Response
    {
        if (!$this->isCsrfTokenValid(self::REORDER_TOKEN, $request->token)) {
            return new JsonResponse(['error' => 'invalid_token'], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->management->reorder($this->household->id(), array_map(Uuid::fromString(...), $request->ids));
        } catch (CategoryRuleViolation $violation) {
            return new JsonResponse(['error' => $violation->getMessage()], Response::HTTP_CONFLICT);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function find(string $id): Category
    {
        try {
            return $this->management->get($this->household->id(), Uuid::fromString($id));
        } catch (CategoryNotFound|\InvalidArgumentException) {
            throw $this->createNotFoundException();
        }
    }

    /**
     * Mit Fehlern 422 zurückgeben, damit Turbo das Formular erneut anzeigt.
     *
     * @param FormInterface<CategoryData> $form
     * @param array<string, mixed>        $parameters
     */
    private function renderForm(string $template, FormInterface $form, array $parameters = []): Response
    {
        $status = $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render($template, ['form' => $form] + $parameters, new Response(status: $status));
    }
}

<?php

declare(strict_types=1);

namespace App\Recipe\UI\Controller;

use App\Recipe\Application\DishEditor;
use App\Recipe\Application\DishNotFound;
use App\Recipe\Domain\Model\Dish;
use App\Recipe\Infrastructure\Doctrine\DishRepository;
use App\Recipe\UI\Form\DishData;
use App\Recipe\UI\Form\DishType;
use App\Shared\Application\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Tab „Gerichte“: Übersicht, Detail, Anlegen, Bearbeiten, Löschen. Normale Seiten mit Verbindung (Spezifikation
 * Phase 2, Kapitel 2).
 */
#[Route('/gerichte', name: 'recipe_dishes')]
final class DishController extends AbstractController
{
    public const string DELETE_TOKEN = 'delete-dish';
    private const string ID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(
        private readonly DishEditor $editor,
        private readonly DishRepository $dishes,
        private readonly CurrentUser $user,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $dishes = $this->dishes->findAllActive($this->user->householdId());

        // Schlagworte für die Filter-Chips: jedes einmal (unabhängig von Groß-/Kleinschreibung), alphabetisch
        $tags = [];
        foreach ($dishes as $dish) {
            foreach ($dish->getTags() as $tag) {
                $tags[mb_strtolower($tag)] ??= $tag;
            }
        }
        ksort($tags, \SORT_NATURAL);

        return $this->render('recipe/dishes.html.twig', ['dishes' => $dishes, 'tags' => array_values($tags)]);
    }

    #[Route('/neu', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(DishType::class, new DishData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dish = $this->editor->create($this->user->householdId(), $this->user->id(), $this->data($form)->toInput());

            return $this->redirectToRoute('recipe_dishes_show', ['id' => $dish->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('recipe/dish_new.html.twig', $form);
    }

    #[Route('/{id}', name: '_show', requirements: ['id' => self::ID], methods: ['GET'])]
    public function show(string $id): Response
    {
        return $this->render('recipe/dish_show.html.twig', ['dish' => $this->find($id)]);
    }

    #[Route('/{id}/bearbeiten', name: '_edit', requirements: ['id' => self::ID], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $dish = $this->find($id);
        $form = $this->createForm(DishType::class, DishData::fromDish($dish));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->editor->update($this->user->householdId(), $dish->getId(), $this->data($form)->toInput());

            return $this->redirectToRoute('recipe_dishes_show', ['id' => $dish->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('recipe/dish_edit.html.twig', $form, ['dish' => $dish, 'delete_token' => self::DELETE_TOKEN]);
    }

    #[Route('/{id}/loeschen', name: '_delete', requirements: ['id' => self::ID], methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        $dish = $this->find($id);
        if (!$this->isCsrfTokenValid(self::DELETE_TOKEN, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $this->editor->delete($this->user->householdId(), $dish->getId());
        $this->addFlash('success', \sprintf('„%s“ gelöscht.', $dish->getName()));

        return $this->redirectToRoute('recipe_dishes', status: Response::HTTP_SEE_OTHER);
    }

    private function find(string $id): Dish
    {
        try {
            return $this->editor->get($this->user->householdId(), Uuid::fromString($id));
        } catch (DishNotFound) {
            throw $this->createNotFoundException();
        }
    }

    /**
     * @param FormInterface<DishData> $form
     */
    private function data(FormInterface $form): DishData
    {
        return $form->getData();
    }

    /**
     * @param FormInterface<DishData> $form
     * @param array<string, mixed>    $parameters
     */
    private function renderForm(string $template, FormInterface $form, array $parameters = []): Response
    {
        $status = $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render($template, ['form' => $form] + $parameters, new Response(status: $status));
    }
}

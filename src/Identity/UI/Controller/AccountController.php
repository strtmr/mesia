<?php

declare(strict_types=1);

namespace App\Identity\UI\Controller;

use App\Identity\Application\UserRegistration;
use App\Identity\Domain\Model\User;
use App\Identity\UI\Form\DisplayNameData;
use App\Identity\UI\Form\DisplayNameType;
use App\Identity\UI\Form\PasswordChangeData;
use App\Identity\UI\Form\PasswordChangeType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;

/**
 * „Mehr → Konto“: eigenen Namen und eigenes Passwort ändern. Normale Seiten mit Verbindung.
 */
#[Route('/mehr/konto', name: 'account')]
final class AccountController extends AbstractController
{
    public function __construct(private readonly UserRegistration $registration)
    {
    }

    #[Route('/name', name: '_name', methods: ['GET', 'POST'])]
    public function name(Request $request, #[CurrentUser] User $user): Response
    {
        $data = new DisplayNameData();
        $data->displayName = $user->getDisplayName();
        $form = $this->createForm(DisplayNameType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->registration->renameUser($user, (string) $data->displayName);
            $this->addFlash('success', 'Name geändert.');

            return $this->redirectToRoute('app_more', status: Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('identity/account_name.html.twig', $form);
    }

    /**
     * Der neue Hash meldet alle anderen Sitzungen und „Angemeldet bleiben“-Cookies ab. Dieses Gerät wird gleich wieder
     * angemeldet, mit neuem Cookie, sonst flöge man hier beim nächsten Aufruf selbst hinaus.
     */
    #[Route('/passwort', name: '_password', methods: ['GET', 'POST'])]
    public function password(Request $request, #[CurrentUser] User $user, Security $security): Response
    {
        $data = new PasswordChangeData();
        $form = $this->createForm(PasswordChangeType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->registration->setPassword($user, (string) $data->newPassword);
            $security->login($user, 'form_login', 'main', [(new RememberMeBadge())->enable()]);
            $this->addFlash('success', 'Passwort geändert. Auf anderen Geräten musst du dich neu anmelden.');

            return $this->redirectToRoute('app_more', status: Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('identity/account_password.html.twig', $form);
    }

    /**
     * @param FormInterface<covariant object> $form
     */
    private function renderForm(string $template, FormInterface $form): Response
    {
        $status = $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render($template, ['form' => $form], new Response(status: $status));
    }
}

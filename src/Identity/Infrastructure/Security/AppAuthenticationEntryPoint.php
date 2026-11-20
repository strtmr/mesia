<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Nicht angemeldet: Webseiten leiten zum Login weiter, die JSON-API antwortet mit 401. Der Offline-Client behält
 * dann seine ausstehenden Änderungen und zeigt "Bitte neu anmelden".
 */
final readonly class AppAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    private const string FIREWALL = 'main';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            // Symfony hat sich die URL schon als Ziel nach dem Login gemerkt (der Sync-Request gilt nicht als
            // XMLHttpRequest). Sonst landet man nach dem Anmelden auf dem JSON von /api/sync/pull statt in der App.
            if ($request->hasSession()) {
                $this->removeTargetPath($request->getSession(), self::FIREWALL);
            }

            return new JsonResponse(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}

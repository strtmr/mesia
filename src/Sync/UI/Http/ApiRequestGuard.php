<?php

declare(strict_types=1);

namespace App\Sync\UI\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CSRF-Schutz für die JSON-API: Schreibende Requests unter /api/ brauchen den Header
 * "X-Requested-With: mesia". Ein Origin-Header muss, falls vorhanden, zur eigenen Domain passen.
 *
 * Ein fremdes Formular kann keinen eigenen Header setzen, und fremde Skripte scheitern an CORS. Zusammen mit dem
 * SameSite=Lax-Cookie genügt das ohne Token.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 30)]
final readonly class ApiRequestGuard
{
    public const string HEADER = 'X-Requested-With';
    public const string HEADER_VALUE = 'mesia';

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api/') || $request->isMethodSafe()) {
            return;
        }

        if (self::HEADER_VALUE !== $request->headers->get(self::HEADER)) {
            $event->setResponse(new JsonResponse(['error' => 'missing_request_header'], 403));

            return;
        }

        $origin = $request->headers->get('Origin');
        if (null !== $origin && $origin !== $request->getSchemeAndHttpHost()) {
            $event->setResponse(new JsonResponse(['error' => 'invalid_origin'], 403));
        }
    }
}

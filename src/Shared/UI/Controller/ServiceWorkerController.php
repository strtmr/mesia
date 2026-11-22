<?php

declare(strict_types=1);

namespace App\Shared\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liefert den Service Worker aus (Spezifikation Kapitel 8).
 *
 * Er wird gerendert statt als statische Datei abgelegt, damit er die versionierten Asset-URLs von AssetMapper kennt
 * und vorab cachen kann. Ändert sich ein Asset, ändert sich auch der Inhalt von /sw.js. Der Browser installiert dann
 * die neue Version.
 */
final class ServiceWorkerController extends AbstractController
{
    /** Statische Dateien aus public/, die offline gebraucht werden. */
    private const array STATIC_FILES = [
        '/offline.html',
        '/manifest.webmanifest',
        '/icons/icon.svg',
        '/icons/favicon.svg',
        '/icons/icon-192.png',
        '/icons/apple-touch-icon.png',
    ];

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        #[Autowire('%app.version%')]
        private readonly string $appVersion,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    #[Route('/sw.js', name: 'app_service_worker', methods: ['GET'])]
    public function __invoke(): Response
    {
        $assets = [];
        foreach ($this->assetMapper->allAssets() as $asset) {
            $assets[] = $asset->publicPath;
        }
        sort($assets);

        // Versionskennung: ändert sich mit jedem Asset (Hash im Dateinamen) und jeder statischen Datei
        $fingerprint = $this->appVersion."\n".implode("\n", $assets);
        foreach (self::STATIC_FILES as $file) {
            $fingerprint .= "\n".(hash_file('xxh128', $this->publicDir.$file) ?: '');
        }

        $response = $this->render('pwa/sw.js.twig', [
            'version' => substr(hash('xxh128', $fingerprint), 0, 12),
            'shell_url' => $this->generateUrl('shopping_list'),
            'assets' => $assets,
            'static_files' => self::STATIC_FILES,
        ]);
        $response->headers->set('Content-Type', 'text/javascript; charset=UTF-8');
        // Nie aus dem HTTP-Cache: Der Browser soll neue Versionen sofort bemerken.
        $response->headers->set('Cache-Control', 'no-cache, private');

        return $response;
    }
}

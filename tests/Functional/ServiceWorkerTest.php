<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;

final class ServiceWorkerTest extends WebTestCase
{
    public function testServiceWorkerIsPublicAndNeverCached(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sw.js');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/javascript; charset=UTF-8');
        self::assertStringContainsString('no-cache', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testPrecachesAllVersionedAssetsAndTheListShell(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sw.js');
        $script = (string) $client->getResponse()->getContent();

        $assetMapper = self::getContainer()->get(AssetMapperInterface::class);
        foreach (['app.js', 'shopping/ui/app.js', 'shopping/core/sync.js', 'styles/app.css', 'vendor/alpinejs/alpinejs.index.js'] as $logicalPath) {
            $publicPath = $assetMapper->getPublicPath($logicalPath);
            self::assertNotNull($publicPath, $logicalPath);
            self::assertStringContainsString('"'.$publicPath.'"', $script, $logicalPath);
        }
        self::assertStringContainsString('const SHELL_URL = "/einkauf";', $script);
        self::assertStringContainsString('"/offline.html"', $script);
        self::assertMatchesRegularExpression('/const VERSION = "[0-9a-f]{12}";/', $script);
    }

    public function testVersionStaysTheSameWhileNothingChanges(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sw.js');
        $first = $client->getResponse()->getContent();
        $client->request('GET', '/sw.js');

        self::assertSame($first, $client->getResponse()->getContent());
    }
}

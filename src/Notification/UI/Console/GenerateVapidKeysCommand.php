<?php

declare(strict_types=1);

namespace App\Notification\UI\Console;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Erzeugt ein VAPID-Schlüsselpaar für Web Push. Einmal pro Umgebung, Ausgabe in .env.local übernehmen.
 * Ein neues Paar macht alle bestehenden Abos ungültig (Geräte müssen Mitteilungen neu einschalten).
 */
#[AsCommand(name: 'app:push:generate-keys', description: 'Erzeugt VAPID-Schlüssel für Push-Nachrichten')]
final readonly class GenerateVapidKeysCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $keys = VAPID::createVapidKeys();
        $io->writeln('In .env.local (bzw. shared/.env.local auf dem Server) eintragen:');
        $io->newLine();
        $io->writeln('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $io->writeln('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return Command::SUCCESS;
    }
}

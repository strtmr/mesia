<?php

declare(strict_types=1);

namespace App\Notification\UI\Console;

use App\Notification\Application\PushDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

/**
 * Für den Cronjob (jede Minute): verschickt fällige Benachrichtigungen. Läuft nie doppelt (Lock).
 */
#[AsCommand(name: 'app:push:send', description: 'Verschickt fällige Push-Nachrichten (Cronjob, jede Minute)')]
final readonly class SendPushCommand
{
    public function __construct(
        private PushDispatcher $dispatcher,
        private LockFactory $locks,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $lock = $this->locks->createLock('app-push-send', ttl: 300);
        if (!$lock->acquire()) {
            $io->writeln('Läuft bereits.');

            return Command::SUCCESS;
        }

        try {
            $stats = $this->dispatcher->dispatch();
        } finally {
            $lock->release();
        }

        if ($io->isVerbose() || array_sum($stats) > 0) {
            $io->writeln(\sprintf('Gesendet: %d, entfernt: %d, fehlgeschlagen: %d', $stats['sent'], $stats['removed'], $stats['failed']));
        }

        return Command::SUCCESS;
    }
}

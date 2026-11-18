<?php

declare(strict_types=1);

namespace App\Shared\Application\ChangeLog;

interface ChangeRecorder
{
    /**
     * Schreibt einen Eintrag ins Änderungsprotokoll.
     *
     * Muss innerhalb der Transaktion aufgerufen werden, die auch die eigentliche Änderung schreibt.
     */
    public function record(ChangeRecord $record): void;
}

<?php

declare(strict_types=1);

namespace App\Tests\Double\Shared;

use App\Shared\Application\ChangeLog\ChangeRecord;
use App\Shared\Application\ChangeLog\ChangeRecorder;

final class InMemoryChangeRecorder implements ChangeRecorder
{
    /** @var list<ChangeRecord> */
    public array $records = [];

    public function record(ChangeRecord $record): void
    {
        $this->records[] = $record;
    }
}

<?php

declare(strict_types=1);

namespace App\Admin\Application;

final readonly class Totals
{
    public function __construct(
        public int $households,
        public int $users,
        public int $openItems,
        public int $purchases,
    ) {
    }
}

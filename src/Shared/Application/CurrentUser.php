<?php

declare(strict_types=1);

namespace App\Shared\Application;

use Symfony\Component\Uid\Uuid;

/**
 * Angemeldeter Benutzer (nur IDs). Umgesetzt im Modul Identity, damit andere Module den Benutzer nicht kennen müssen.
 */
interface CurrentUser
{
    /** @throws \LogicException ohne angemeldeten Benutzer */
    public function id(): Uuid;

    /** @throws \LogicException ohne angemeldeten Benutzer */
    public function householdId(): Uuid;
}

<?php

declare(strict_types=1);

namespace App\Shared\Application;

use Symfony\Component\Uid\Uuid;

/**
 * Haushalt des angemeldeten Benutzers. Umgesetzt im Modul Identity, damit andere Module den Benutzer nicht kennen müssen.
 */
interface CurrentHousehold
{
    /**
     * @throws \LogicException ohne angemeldeten Benutzer (Seiten hinter dem Login rufen das nur angemeldet auf)
     */
    public function id(): Uuid;
}

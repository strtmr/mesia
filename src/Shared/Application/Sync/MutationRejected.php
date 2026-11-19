<?php

declare(strict_types=1);

namespace App\Shared\Application\Sync;

/**
 * Fachlich ungültige Mutation. Die Meldung geht an den Client und sollte für Menschen verständlich sein.
 */
final class MutationRejected extends \DomainException
{
}

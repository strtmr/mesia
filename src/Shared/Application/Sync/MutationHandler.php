<?php

declare(strict_types=1);

namespace App\Shared\Application\Sync;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Verarbeitet einen Mutationstyp aus dem Sync-Push (z. B. "item.add").
 *
 * Der Dispatcher wandelt das JSON-Payload vorher in die Klasse aus payloadClass() um und validiert es.
 * handle() läuft in einer Transaktion, die den HouseholdWriteLock bereits hält.
 *
 * @template TPayload of object
 */
#[AutoconfigureTag(self::TAG)]
interface MutationHandler
{
    public const string TAG = 'app.sync.mutation_handler';

    public static function mutationType(): string;

    /**
     * @return class-string<TPayload>
     */
    public function payloadClass(): string;

    /**
     * @param TPayload $payload
     *
     * @throws MutationRejected
     */
    public function handle(object $payload, MutationContext $context): MutationOutcome;
}

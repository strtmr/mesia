<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shopping\Domain\ContributionSource;
use App\Shopping\Domain\Quantity;

/**
 * "item.reactivate": erledigten Eintrag wieder auf die Liste setzen (Tippen auf einen Eintrag unter "Erledigt").
 * Ist der Eintrag gar nicht erledigt, wird nur der Beitrag ergänzt.
 *
 * @implements MutationHandler<ReactivateItemPayload>
 */
final readonly class ReactivateItemHandler implements MutationHandler
{
    public function __construct(private ItemLookup $lookup)
    {
    }

    public static function mutationType(): string
    {
        return 'item.reactivate';
    }

    public function payloadClass(): string
    {
        return ReactivateItemPayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $item = $this->lookup->get($context->householdId, $payload->itemId);
        $contribution = $payload->contribution;

        $item->addContribution($contribution->id, ContributionSource::Manual, null, Quantity::fromFloat($contribution->quantity), $contribution->unit, $context->actorId, $context->at, $context->now);

        return MutationOutcome::applied();
    }
}

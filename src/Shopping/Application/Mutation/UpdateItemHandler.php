<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Catalog\Domain\Unit;
use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shared\Application\Sync\MutationRejected;
use App\Shopping\Domain\Quantity;

/**
 * "item.update": Name, Notiz oder manuelle Menge ändern (Bearbeiten-Sheet). Last-Writer-Wins pro Feld.
 *
 * @implements MutationHandler<UpdateItemPayload>
 */
final readonly class UpdateItemHandler implements MutationHandler
{
    private const array ALLOWED_FIELDS = ['name', 'note', 'qty_override', 'unit_override', 'store_ids', 'valid_until'];

    public function __construct(private ItemLookup $lookup, private ItemExtras $extras)
    {
    }

    public static function mutationType(): string
    {
        return 'item.update';
    }

    public function payloadClass(): string
    {
        return UpdateItemPayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $fields = $payload->fields;
        $unknown = array_diff(array_keys($fields), self::ALLOWED_FIELDS);
        if ([] !== $unknown) {
            throw new MutationRejected(\sprintf('Unbekannte Felder: %s', implode(', ', $unknown)));
        }

        $item = $this->lookup->get($context->householdId, $payload->itemId);

        try {
            if (\array_key_exists('name', $fields)) {
                $item->rename(self::string($fields['name'], 'name'), $context->actorId, $context->now);
            }
            if (\array_key_exists('note', $fields)) {
                $item->changeNote(null === $fields['note'] ? null : self::string($fields['note'], 'note'), $context->actorId, $context->now);
            }
            if (\array_key_exists('qty_override', $fields) || \array_key_exists('unit_override', $fields)) {
                $quantity = $fields['qty_override'] ?? null;
                if (null !== $quantity && !\is_int($quantity) && !\is_float($quantity)) {
                    throw new MutationRejected('qty_override muss eine Zahl oder null sein.');
                }
                $unit = $fields['unit_override'] ?? null;
                $unitEnum = null === $unit ? null : (\is_string($unit) ? Unit::tryFrom($unit) : null);
                if (null !== $unit && null === $unitEnum) {
                    throw new MutationRejected('Unbekannte Einheit.');
                }
                $item->overrideQuantity(Quantity::fromFloat(null === $quantity ? null : (float) $quantity), $unitEnum, $context->actorId, $context->now);
            }
            if (\array_key_exists('store_ids', $fields)) {
                $item->assignStores($this->extras->storeIds($context->householdId, $fields['store_ids']), $context->actorId, $context->now);
            }
            if (\array_key_exists('valid_until', $fields)) {
                $item->setValidUntil(ItemExtras::date($fields['valid_until']), $context->actorId, $context->now);
            }
        } catch (\InvalidArgumentException $exception) {
            throw new MutationRejected($exception->getMessage(), previous: $exception);
        }

        return MutationOutcome::applied();
    }

    private static function string(mixed $value, string $field): string
    {
        if (!\is_string($value)) {
            throw new MutationRejected(\sprintf('%s muss ein Text sein.', $field));
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Catalog\Application;

/**
 * Fachlicher Fehler beim Verwalten von Kategorien. Die Meldung ist für Benutzer gedacht.
 */
final class CategoryRuleViolation extends \DomainException
{
}

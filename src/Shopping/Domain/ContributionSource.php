<?php

declare(strict_types=1);

namespace App\Shopping\Domain;

/**
 * Woher ein Beitrag zu einem Listeneintrag stammt. Jede Quelle ändert nur ihre eigenen Beiträge.
 */
enum ContributionSource: string
{
    case Manual = 'manual';
    case MealPlan = 'meal_plan';
    case Recipe = 'recipe';
    case Pantry = 'pantry';
    case Suggestion = 'suggestion';
    case Import = 'import';
}

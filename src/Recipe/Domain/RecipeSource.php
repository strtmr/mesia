<?php

declare(strict_types=1);

namespace App\Recipe\Domain;

enum RecipeSource: string
{
    case Manual = 'manual';
    case Import = 'import';
}

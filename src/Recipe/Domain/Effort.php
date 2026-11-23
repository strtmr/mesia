<?php

declare(strict_types=1);

namespace App\Recipe\Domain;

/**
 * Wie aufwendig ein Gericht ist. Wichtig später für den Wochenplan (z. B. unter der Woche nur „schnell“).
 */
enum Effort: string
{
    case Quick = 'quick';
    case Normal = 'normal';
    case Elaborate = 'elaborate';
}

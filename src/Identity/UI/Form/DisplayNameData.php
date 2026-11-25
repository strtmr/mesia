<?php

declare(strict_types=1);

namespace App\Identity\UI\Form;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulardaten „Name ändern“ (Mehr → Konto, Admin → Person bearbeiten).
 */
final class DisplayNameData
{
    #[Assert\NotBlank(message: 'Bitte einen Namen eingeben.')]
    #[Assert\Length(max: 100, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $displayName = null;
}

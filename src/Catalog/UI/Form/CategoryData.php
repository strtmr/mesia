<?php

declare(strict_types=1);

namespace App\Catalog\UI\Form;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulardaten „Kategorie anlegen/ändern“.
 */
final class CategoryData
{
    #[Assert\NotBlank(message: 'Bitte einen Namen eingeben.')]
    #[Assert\Length(max: 60, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Bitte ein Symbol eingeben, z. B. ein Emoji.')]
    #[Assert\Length(max: 16, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $icon = '🏷️';
}

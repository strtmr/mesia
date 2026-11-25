<?php

declare(strict_types=1);

namespace App\Admin\UI\Form;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulardaten „Haushalt bearbeiten“.
 */
final class HouseholdData
{
    #[Assert\NotBlank(message: 'Bitte einen Namen für den Haushalt eingeben.')]
    #[Assert\Length(max: 100, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $name = null;
}

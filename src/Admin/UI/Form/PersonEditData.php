<?php

declare(strict_types=1);

namespace App\Admin\UI\Form;

use App\Identity\Application\UserRegistration;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulardaten „Person bearbeiten“: Name, optional ein neues Passwort (leer = unverändert).
 */
final class PersonEditData
{
    #[Assert\NotBlank(message: 'Bitte einen Namen eingeben.')]
    #[Assert\Length(max: 100, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $displayName = null;

    #[Assert\Length(min: UserRegistration::MIN_PASSWORD_LENGTH, minMessage: 'Mindestens {{ limit }} Zeichen.')]
    public ?string $password = null;
}

<?php

declare(strict_types=1);

namespace App\Admin\UI\Form;

use App\Identity\Application\UserRegistration;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulardaten „Person anlegen“, beim neuen Haushalt zusätzlich mit dessen Namen.
 */
final class PersonData
{
    #[Assert\NotBlank(message: 'Bitte einen Namen für den Haushalt eingeben.', groups: ['household'])]
    #[Assert\Length(max: 100, maxMessage: 'Höchstens {{ limit }} Zeichen.', groups: ['household'])]
    public ?string $householdName = null;

    #[Assert\NotBlank(message: 'Bitte einen Namen eingeben.')]
    #[Assert\Length(max: 100, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $displayName = null;

    #[Assert\NotBlank(message: 'Bitte eine E-Mail-Adresse eingeben.')]
    #[Assert\Email(message: 'Das ist keine gültige E-Mail-Adresse.')]
    #[Assert\Length(max: 180, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Bitte ein Passwort vergeben.')]
    #[Assert\Length(min: UserRegistration::MIN_PASSWORD_LENGTH, minMessage: 'Mindestens {{ limit }} Zeichen.')]
    public ?string $password = null;
}

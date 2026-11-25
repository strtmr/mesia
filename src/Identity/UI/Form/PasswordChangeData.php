<?php

declare(strict_types=1);

namespace App\Identity\UI\Form;

use App\Identity\Application\UserRegistration;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulardaten „Passwort ändern“: Das aktuelle Passwort schützt vor fremden Händen an einem entsperrten Gerät.
 */
final class PasswordChangeData
{
    #[Assert\NotBlank(message: 'Bitte das aktuelle Passwort eingeben.')]
    #[SecurityAssert\UserPassword(message: 'Das aktuelle Passwort stimmt nicht.')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank(message: 'Bitte ein neues Passwort eingeben.')]
    #[Assert\Length(min: UserRegistration::MIN_PASSWORD_LENGTH, minMessage: 'Mindestens {{ limit }} Zeichen.')]
    public ?string $newPassword = null;
}

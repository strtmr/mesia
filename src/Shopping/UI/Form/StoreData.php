<?php

declare(strict_types=1);

namespace App\Shopping\UI\Form;

use App\Shopping\Domain\Model\Store;
use Symfony\Component\Validator\Constraints as Assert;

final class StoreData
{
    #[Assert\NotBlank(message: 'Bitte einen Namen eingeben.')]
    #[Assert\Length(max: Store::NAME_MAX_LENGTH, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    #[Assert\Regex(pattern: '/@/', match: false, message: 'Bitte ohne „@“ (das steht in der Schnelleingabe vor dem Laden).')]
    public ?string $name = null;
}

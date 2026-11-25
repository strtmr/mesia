<?php

declare(strict_types=1);

namespace App\Identity\UI\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PasswordChangeData>
 */
final class PasswordChangeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $input = static fn (string $autocomplete): array => ['label' => false, 'attr' => ['class' => 'field__input', 'autocomplete' => $autocomplete]];

        $builder
            ->add('currentPassword', PasswordType::class, $input('current-password'))
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Die Passwörter stimmen nicht überein.',
                'first_options' => $input('new-password'),
                'second_options' => $input('new-password'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PasswordChangeData::class]);
    }
}

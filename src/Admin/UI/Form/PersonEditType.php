<?php

declare(strict_types=1);

namespace App\Admin\UI\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PersonEditData>
 */
final class PersonEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $input = static fn (array $attr = []): array => ['label' => false, 'attr' => ['class' => 'field__input', 'autocomplete' => 'off', ...$attr]];

        $builder
            ->add('displayName', TextType::class, $input(['maxlength' => 100, 'autocapitalize' => 'words']))
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => false,
                'invalid_message' => 'Die Passwörter stimmen nicht überein.',
                'first_options' => $input(['autocomplete' => 'new-password']),
                'second_options' => $input(['autocomplete' => 'new-password']),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PersonEditData::class]);
    }
}

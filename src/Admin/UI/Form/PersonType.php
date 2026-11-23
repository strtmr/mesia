<?php

declare(strict_types=1);

namespace App\Admin\UI\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Option with_household: zusätzlich der Name eines neuen Haushalts.
 *
 * @extends AbstractType<PersonData>
 */
final class PersonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $input = static fn (array $attr = []): array => ['label' => false, 'attr' => ['class' => 'field__input', 'autocomplete' => 'off', ...$attr]];

        if (true === $options['with_household']) {
            $builder->add('householdName', TextType::class, $input(['maxlength' => 100, 'autocapitalize' => 'words']));
        }
        $builder
            ->add('displayName', TextType::class, $input(['maxlength' => 100, 'autocapitalize' => 'words']))
            ->add('email', EmailType::class, $input(['maxlength' => 180, 'autocapitalize' => 'none', 'inputmode' => 'email']))
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Die Passwörter stimmen nicht überein.',
                'first_options' => $input(['autocomplete' => 'new-password']),
                'second_options' => $input(['autocomplete' => 'new-password']),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PersonData::class,
            'with_household' => false,
            'validation_groups' => static fn (\Symfony\Component\Form\FormInterface $form): array => true === $form->getConfig()->getOption('with_household') ? ['Default', 'household'] : ['Default'],
        ]);
        $resolver->setAllowedTypes('with_household', 'bool');
    }
}

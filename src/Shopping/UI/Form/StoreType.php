<?php

declare(strict_types=1);

namespace App\Shopping\UI\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<StoreData>
 */
final class StoreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => false,
            'attr' => ['class' => 'field__input', 'maxlength' => 40, 'autocomplete' => 'off', 'enterkeyhint' => 'done', 'autocapitalize' => 'words'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => StoreData::class]);
    }
}

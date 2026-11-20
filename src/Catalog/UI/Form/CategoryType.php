<?php

declare(strict_types=1);

namespace App\Catalog\UI\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CategoryData>
 */
final class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('icon', TextType::class, [
                'label' => false,
                'attr' => ['class' => 'field__input field__input--icon', 'maxlength' => 16, 'autocomplete' => 'off'],
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['class' => 'field__input', 'maxlength' => 60, 'autocomplete' => 'off', 'enterkeyhint' => 'done', 'autocapitalize' => 'sentences'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CategoryData::class]);
    }
}

<?php

declare(strict_types=1);

namespace App\Recipe\UI\Form;

use App\Recipe\Domain\Effort;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<DishData>
 */
final class DishType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $field = static fn (array $attr = []): array => ['label' => false, 'required' => false, 'attr' => ['class' => 'field__input', ...$attr]];
        $minutes = $field(['inputmode' => 'numeric', 'min' => 0, 'placeholder' => 'Min.']);

        $builder
            ->add('name', TextType::class, $field(['maxlength' => 100, 'autocapitalize' => 'sentences', 'enterkeyhint' => 'next']))
            ->add('servings', IntegerType::class, $field(['inputmode' => 'numeric', 'min' => 1, 'max' => 99]))
            ->add('prepMinutes', IntegerType::class, $minutes)
            ->add('cookMinutes', IntegerType::class, $minutes)
            ->add('effort', EnumType::class, [
                ...$field(),
                'class' => Effort::class,
                'placeholder' => 'recipe.effort.none',
                'choice_label' => static fn (Effort $effort): string => 'recipe.effort.'.$effort->value,
            ])
            ->add('tags', TextType::class, $field(['maxlength' => 400, 'placeholder' => 'recipe.form.tags_placeholder', 'autocapitalize' => 'none']))
            ->add('ingredients', TextareaType::class, $field(['rows' => 8, 'placeholder' => 'recipe.form.ingredients_placeholder', 'autocapitalize' => 'sentences']))
            ->add('steps', TextareaType::class, $field(['rows' => 8, 'placeholder' => 'recipe.form.steps_placeholder', 'autocapitalize' => 'sentences']))
            ->add('note', TextareaType::class, $field(['rows' => 3]))
            ->add('sourceUrl', UrlType::class, [...$field(['inputmode' => 'url', 'placeholder' => 'https://…', 'autocapitalize' => 'none']), 'default_protocol' => 'https']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DishData::class]);
    }
}

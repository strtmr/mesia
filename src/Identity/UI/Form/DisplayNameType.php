<?php

declare(strict_types=1);

namespace App\Identity\UI\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<DisplayNameData>
 */
final class DisplayNameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('displayName', TextType::class, [
            'label' => false,
            'attr' => ['class' => 'field__input', 'maxlength' => 100, 'autocomplete' => 'name', 'enterkeyhint' => 'done', 'autocapitalize' => 'words'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DisplayNameData::class]);
    }
}

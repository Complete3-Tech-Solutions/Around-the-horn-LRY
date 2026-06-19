<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Moderator "change my password" form. New password + confirm only — the
 * moderator is already authenticated, so the current password is not asked for.
 * Unmapped (the entity stores the hash; the controller hashes the plain value).
 */
class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => ['label' => 'New password'],
            'second_options' => ['label' => 'Confirm new password'],
            'invalid_message' => 'The two passwords do not match.',
            'constraints' => [
                new NotBlank(message: 'Please enter a new password.'),
                new Length(min: 8, max: 4096, minMessage: 'Use at least {{ limit }} characters.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}

<?php

namespace App\Form;

use App\Entity\Founder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Moderator founder editor. Name / company / charity are mapped to the entity;
 * the headshot is an unmapped optional upload (handled in the controller so the
 * existing image is kept when no new file is chosen). Founder ballot position
 * is managed automatically, not exposed here.
 */
class FounderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Founder name',
            ])
            ->add('company', TextType::class, [
                'label' => 'Company / role',
                'required' => false,
            ])
            ->add('charity', TextType::class, [
                'label' => 'Charity (where the donation goes if they win)',
                'required' => false,
            ])
            ->add('headshotFile', FileType::class, [
                'label' => 'Headshot (JPG/PNG/WebP — optional, replaces current)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '4M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
                        'mimeTypesMessage' => 'Please upload a JPG, PNG, WebP or SVG image.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Founder::class,
        ]);
    }
}

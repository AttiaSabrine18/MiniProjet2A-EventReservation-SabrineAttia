<?php
// src/Form/EventType.php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'événement',
                'attr'  => [
                    'placeholder' => 'Ex: Concert Jazz Night',
                    'class'       => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est obligatoire']),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => [
                    'placeholder' => 'Décrivez votre événement...',
                    'class'       => 'form-control',
                    'rows'        => 4,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire']),
                ],
            ])

            ->add('date', DateTimeType::class, [
                'label'  => 'Date et heure',
                'widget' => 'single_text',
                'attr'   => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'La date est obligatoire']),
                ],
            ])

            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'attr'  => [
                    'placeholder' => 'Ex: Salle des fêtes, Tunis',
                    'class'       => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le lieu est obligatoire']),
                ],
            ])

            ->add('seats', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr'  => [
                    'placeholder' => 'Ex: 100',
                    'class'       => 'form-control',
                    'min'         => 1,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nombre de places est obligatoire']),
                    new Positive(['message' => 'Le nombre de places doit être positif']),
                ],
            ])

            // ← FileType au lieu de UrlType — permet upload local
            ->add('imageFile', FileType::class, [
                'label'    => 'Image de l\'événement',
                'mapped'   => false, // ← pas lié directement à l'entité
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize'          => '2M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Format accepté : JPG, PNG, WEBP',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
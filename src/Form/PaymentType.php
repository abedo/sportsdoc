<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Place;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('place', EntityType::class, ['class' => Place::class, 'choices' => $options['places'], 'label' => 'Miejsce'])
            ->add('user', EntityType::class, ['class' => User::class, 'choices' => $options['users'], 'label' => 'Osoba'])
            ->add('date', DateType::class, ['label' => 'Data wpłaty', 'widget' => 'single_text'])
            ->add('amount', IntegerType::class, ['label' => 'Kwota', 'attr' => ['min' => 1]])
            ->add('confirmed', CheckboxType::class, ['label' => 'Potwierdzona', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Payment::class, 'places' => [], 'users' => []]);
        $resolver->setAllowedTypes('places', 'array');
        $resolver->setAllowedTypes('users', 'array');
    }
}

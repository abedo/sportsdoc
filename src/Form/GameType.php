<?php

namespace App\Form;

use App\Entity\Game;
use App\Entity\Place;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('createdAt', DateType::class, ['label' => 'Data gry', 'widget' => 'single_text'])
            ->add('cost', IntegerType::class, ['label' => 'Koszt (na osobę)', 'attr' => ['min' => 0]])
            ->add('place', EntityType::class, ['class' => Place::class, 'label' => 'Miejsce'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Game::class]);
    }
}

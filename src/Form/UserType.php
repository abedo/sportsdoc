<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'E-mail (taki jak w koncie Google, jeśli ma się zalogować Google)', 'required' => false])
            ->add('fullname', TextType::class, ['label' => 'Imię i nazwisko'])
            ->add('organizer', CheckboxType::class, [
                'label' => 'Organizator (może zakładać miejsca)',
                'required' => false,
                'mapped' => false,
                'data' => \in_array('ROLE_ORGANIZER', $options['data']?->getRoles() ?? [], true),
            ])
            ->add('admin', CheckboxType::class, [
                'label' => 'Administrator serwisu (widzi i zmienia wszystko)',
                'required' => false,
                'mapped' => false,
                'data' => \in_array('ROLE_ADMIN', $options['data']?->getRoles() ?? [], true),
            ])
        ;

        $builder->addEventListener(\Symfony\Component\Form\FormEvents::POST_SUBMIT, static function (\Symfony\Component\Form\FormEvent $event): void {
            /** @var User $user */
            $user = $event->getData();
            $roles = array_values(array_diff($user->getRoles(), ['ROLE_USER', 'ROLE_ORGANIZER', 'ROLE_ADMIN']));
            if ($event->getForm()->get('organizer')->getData()) {
                $roles[] = 'ROLE_ORGANIZER';
            }
            if ($event->getForm()->get('admin')->getData()) {
                $roles[] = 'ROLE_ADMIN';
            }
            $user->setRoles($roles);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}

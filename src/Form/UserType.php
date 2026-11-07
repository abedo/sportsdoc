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
            ->add('email', EmailType::class, ['label' => 'E-mail (taki jak w koncie Google)'])
            ->add('fullname', TextType::class, ['label' => 'Imię i nazwisko'])
            ->add('admin', CheckboxType::class, [
                'label' => 'Organizator (administrator)',
                'required' => false,
                'mapped' => false,
                'data' => \in_array('ROLE_ADMIN', $options['data']?->getRoles() ?? [], true),
            ])
        ;

        $builder->addEventListener(\Symfony\Component\Form\FormEvents::POST_SUBMIT, static function (\Symfony\Component\Form\FormEvent $event): void {
            /** @var User $user */
            $user = $event->getData();
            $roles = array_values(array_diff($user->getRoles(), ['ROLE_USER', 'ROLE_ADMIN']));
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

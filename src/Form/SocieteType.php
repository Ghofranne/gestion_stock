<?php

namespace App\Form;

use App\Entity\Societe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class SocieteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'societe.form.name',
                'translation_domain' => 'societe',
                'attr' => [
                    'placeholder' => 'societe.form.name_placeholder',
                ],
            ])
            ->add('adress', TextType::class, [
                'label' => 'societe.form.address',
                'translation_domain' => 'societe',
                'attr' => [
                    'placeholder' => 'societe.form.address_placeholder',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'societe.form.email',
                'translation_domain' => 'societe',
                'attr' => [
                    'placeholder' => 'societe.form.email_placeholder',
                ],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'societe.form.phone',
                'translation_domain' => 'societe',
                'attr' => [
                    'placeholder' => 'societe.form.phone_placeholder',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Societe::class,
        ]);
    }
}

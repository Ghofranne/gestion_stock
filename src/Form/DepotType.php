<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\Societe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DepotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'depot.form.name',
                'translation_domain' => 'depot',
                'attr' => [
                    'placeholder' => 'depot.form.name_placeholder',
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'depot.form.address',
                'translation_domain' => 'depot',
                'attr' => [
                    'placeholder' => 'depot.form.address_placeholder',
                ],
            ])
            ->add('societe', EntityType::class, [
                'class' => Societe::class,
                'choice_label' => 'nom',
                'placeholder' => 'depot.form.company_placeholder',
                'label' => 'depot.form.company',
                'translation_domain' => 'depot',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Depot::class,
        ]);
    }
}

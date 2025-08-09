<?php

namespace App\Form;

use App\Entity\Equipement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeEquipement', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
            'choices' => [
                'TPE' => 'TPE',
                'SIM' => 'SIM',
                'Carte' => 'Carte',
            ],
            'placeholder' => 'Choisissez le type...',
            'label' => 'Type d\'équipement',
        ])
            ->add('sn')
            ->add('model')
            ->add('imei')
            ->add('numAppel')
            ->add('operateur')
            ->add('referenceCarte')
            ->add('fournisseur')
            ->add('etat')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\depot;
use App\Entity\equipement;
use App\Entity\MouvementStock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MouvementStockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type_mouvement')
            ->add('date')
            ->add('equipement', EntityType::class, [
                'class' => equipement::class,
                'choice_label' => 'id',
            ])
            ->add('depotSource', EntityType::class, [
                'class' => depot::class,
                'choice_label' => 'id',
            ])
            ->add('depotDestination', EntityType::class, [
                'class' => depot::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MouvementStock::class,
        ]);
    }
}

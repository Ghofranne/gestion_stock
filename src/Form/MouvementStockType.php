<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\Equipement;
use App\Entity\MouvementStock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MouvementStockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type_mouvement', ChoiceType::class, [
                'choices' => MouvementStock::getTypesChoices(),
                'placeholder' => 'Sélectionnez le type de mouvement',
                'attr' => ['class' => 'form-control'],
                'label' => 'Type de mouvement'
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'label' => 'Date du mouvement'
            ])
            ->add('equipement', EntityType::class, [
                'class' => Equipement::class,
                'choice_label' => function(Equipement $equipement) {
                    return $equipement->getTypeEquipement() . ' - ' . ($equipement->getImei() ?? $equipement->getSn() ?? $equipement->getReferenceCarte() ?? 'ID: ' . $equipement->getId());
                },
                'placeholder' => 'Sélectionnez un équipement',
                'attr' => ['class' => 'form-control'],
                'label' => 'Équipement'
            ])
            ->add('quantite', IntegerType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Quantité'
                ],
                'label' => 'Quantité'
            ])
            ->add('depotSource', EntityType::class, [
                'class' => Depot::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionnez un dépôt source',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'label' => 'Dépôt source'
            ])
            ->add('depotDestination', EntityType::class, [
                'class' => Depot::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionnez un dépôt destination',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'label' => 'Dépôt destination'
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Commentaire optionnel...'
                ],
                'label' => 'Commentaire'
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

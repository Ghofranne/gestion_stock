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
                // Map translation keys to stored slug values
                'choices' => [
                    'mouvement.types.entree' => MouvementStock::TYPE_ENTREE,
                    'mouvement.types.sortie' => MouvementStock::TYPE_SORTIE,
                    'mouvement.types.transfert' => MouvementStock::TYPE_TRANSFERT,
                    'mouvement.types.ajustement' => MouvementStock::TYPE_AJUSTEMENT,
                    'mouvement.types.retour' => MouvementStock::TYPE_RETOUR,
                ],
                'choice_translation_domain' => 'mouvement',
                'placeholder' => 'mouvement.form.type_placeholder',
                'attr' => ['class' => 'form-control'],
                'label' => 'mouvement.labels.type',
                'translation_domain' => 'mouvement',
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'label' => 'mouvement.labels.date',
                'translation_domain' => 'mouvement',
            ])
            ->add('equipement', EntityType::class, [
                'class' => Equipement::class,
                'choice_label' => function(Equipement $equipement) {
                    return $equipement->getTypeEquipement() . ' - ' . ($equipement->getImei() ?? $equipement->getSn() ?? $equipement->getReferenceCarte() ?? 'ID: ' . $equipement->getId());
                },
                'placeholder' => 'mouvement.form.equipment_placeholder',
                'attr' => ['class' => 'form-control'],
                'label' => 'mouvement.labels.equipment',
                'translation_domain' => 'mouvement',
            ])
            ->add('quantite', IntegerType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'mouvement.placeholders.quantity'
                ],
                'label' => 'mouvement.labels.quantity',
                'translation_domain' => 'mouvement',
            ])
            ->add('depotSource', EntityType::class, [
                'class' => Depot::class,
                'choice_label' => 'nom',
                'placeholder' => 'mouvement.form.depot_source_placeholder',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'label' => 'mouvement.labels.depot_source',
                'translation_domain' => 'mouvement',
            ])
            ->add('depotDestination', EntityType::class, [
                'class' => Depot::class,
                'choice_label' => 'nom',
                'placeholder' => 'mouvement.form.depot_destination_placeholder',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'label' => 'mouvement.labels.depot_destination',
                'translation_domain' => 'mouvement',
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'mouvement.placeholders.comment'
                ],
                'label' => 'mouvement.labels.comment_optional',
                'translation_domain' => 'mouvement',
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

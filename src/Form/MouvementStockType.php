<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\MouvementStock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
            // Filtre optionnel par type d'équipement (non mappé)
            ->add('typeFilter', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'placeholder' => 'mouvement.form.equipment_type_placeholder',
                'choices' => [
                    'Tous' => '',
                    'TPE' => 'TPE',
                    'SIM' => 'SIM',
                    'Carte' => 'Carte',
                    'Autres' => 'Autres',
                ],
                'attr' => ['class' => 'form-control'],
                'label' => 'mouvement.labels.equipment_type',
                'translation_domain' => 'mouvement',
            ])
            // Multi-sélection d'équipements (non mappé). Les options seront chargées en AJAX
            ->add('equipements', EntityType::class, [
                'class' => \App\Entity\Equipement::class,
                'multiple' => true,
                'mapped' => false,
                'choice_label' => function(\App\Entity\Equipement $equipement) {
                    return $equipement->getTypeEquipement() . ' - ' . ($equipement->getImei() ?? $equipement->getSn() ?? $equipement->getReferenceCarte() ?? ('ID: ' . $equipement->getId()));
                },
                'placeholder' => 'mouvement.form.equipment_placeholder',
                'attr' => ['class' => 'form-control'],
                'label' => 'mouvement.labels.equipment',
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
            ]);

        // Add event listener to handle dynamic field requirements
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();
            
            // Add ajustement fields conditionally
            $isAjustement = $data && $data->getTypeMouvement() === MouvementStock::TYPE_AJUSTEMENT;
            
            $form->add('sensAjustement', ChoiceType::class, [
                'mapped' => true,
                'required' => $isAjustement,
                'choices' => [
                    'mouvement.ajustement.sens.entree' => 'entree',
                    'mouvement.ajustement.sens.sortie' => 'sortie',
                ],
                'placeholder' => 'mouvement.ajustement.sens.placeholder',
                'attr' => [
                    'class' => 'form-control ajustement-field',
                    'style' => $isAjustement ? '' : 'display: none;'
                ],
                'label' => 'mouvement.ajustement.sens.label',
                'translation_domain' => 'mouvement',
            ]);

            $form->add('raisonAjustement', TextType::class, [
                'required' => $isAjustement,
                'attr' => [
                    'class' => 'form-control ajustement-field',
                    'placeholder' => 'mouvement.ajustement.raison.placeholder',
                    'style' => $isAjustement ? '' : 'display: none;'
                ],
                'label' => 'mouvement.ajustement.raison.label',
                'translation_domain' => 'mouvement',
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MouvementStock::class,
            'allow_extra_fields' => true,
        ]);
    }

}


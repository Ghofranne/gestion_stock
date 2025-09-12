<?php

namespace App\Form;

use App\Entity\Equipement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;

class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeEquipement', ChoiceType::class, [
                'choices' => [
                    'equipement.types.tpe' => 'TPE',
                    'equipement.types.sim' => 'SIM',
                    'equipement.types.card' => 'Carte',
                    'equipement.types.other' => 'Autres',
                ],
                'choice_translation_domain' => 'equipement',
                'placeholder' => 'equipement.form.type_placeholder',
                'label' => 'equipement.form.type',
                'translation_domain' => 'equipement',
            ])
            ->add('sn', TextType::class, [
                'label' => 'equipement.form.sn',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => ['placeholder' => 'equipement.form.sn_placeholder'],
            ])
            ->add('model', TextType::class, [
                'label' => 'equipement.form.model',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => ['placeholder' => 'equipement.form.model_placeholder'],
            ])
            ->add('imei', TextType::class, [
                'label' => 'equipement.form.imei',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => [
                    'placeholder' => 'equipement.form.imei_placeholder',
                    'maxlength' => 15,
                    'pattern' => '\\d{15}',
                    'inputmode' => 'numeric',
                ],
            ])
            ->add('numAppel', TextType::class, [
                'label' => 'equipement.form.num_call',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => [
                    'placeholder' => 'equipement.form.num_call_placeholder',
                    'pattern' => '\\+?\\d{8,15}',
                    'inputmode' => 'tel',
                ],
            ])
            ->add('operateur', ChoiceType::class, [
                'label' => 'equipement.form.operator',
                'translation_domain' => 'equipement',
                'required' => false,
                'placeholder' => 'Choisir un opérateur',
                'choices' => [
                    'Tunisie Telecom' => 'Tunisie Telecom',
                    'Ooredoo' => 'Ooredoo',
                    'Orange Tunisie' => 'Orange Tunisie',
                ],
            ])
            ->add('referenceCarte', TextType::class, [
                'label' => 'equipement.form.card_ref',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => ['placeholder' => 'equipement.form.card_ref_placeholder'],
            ])
            ->add('fournisseur', TextType::class, [
                'label' => 'equipement.form.supplier',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => ['placeholder' => 'equipement.form.supplier_placeholder'],
            ])
        ;

        // Ajouter le champ 'etat' seulement si demandé (par défaut: visible)
        if ($options['show_state']) {
            $builder->add('etat', TextType::class, [
                'label' => 'equipement.form.state',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => ['placeholder' => 'equipement.form.state_placeholder'],
            ]);
        }
        $builder->add('description', TextareaType::class, [
                'label' => 'equipement.form.description',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => [
                    'placeholder' => 'equipement.form.description_placeholder',
                    'rows' => 3,
                ],
            ])
        ;

        // Normalisation des champs en entrée (trim / formats)
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (!is_array($data)) { return; }
            $normalize = function (?string $v): ?string {
                return is_string($v) ? trim($v) : $v;
            };
            $upper = function (?string $v): ?string {
                return is_string($v) ? mb_strtoupper(trim($v)) : $v;
            };
            $data['imei'] = isset($data['imei']) ? preg_replace('/\s+/', '', $data['imei']) : null;
            $data['sn'] = isset($data['sn']) ? $upper($data['sn']) : null;
            $data['model'] = isset($data['model']) ? $upper($data['model']) : null;
            $data['referenceCarte'] = isset($data['referenceCarte']) ? $upper($data['referenceCarte']) : null;
            $data['numAppel'] = isset($data['numAppel']) ? $normalize($data['numAppel']) : null;
            $data['operateur'] = isset($data['operateur']) ? $upper($data['operateur']) : null;
            $data['fournisseur'] = isset($data['fournisseur']) ? $upper($data['fournisseur']) : null;
            $data['description'] = isset($data['description']) ? $normalize($data['description']) : null;
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
            'show_state' => true, // visible par défaut (ex: édition)
            'validation_groups' => function (FormInterface $form) {
                $data = $form->getData();
                $groups = ['Default'];
                $type = $data?->getTypeEquipement();
                if ($type === 'SIM') { $groups[] = 'sim'; }
                elseif ($type === 'TPE') { $groups[] = 'tpe'; }
                elseif ($type === 'Carte') { $groups[] = 'carte'; }
                else { $groups[] = 'autres'; }
                return $groups;
            },
        ]);
    }
}

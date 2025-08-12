<?php

namespace App\Form;

use App\Entity\Equipement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

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
                'attr' => ['placeholder' => 'equipement.form.imei_placeholder'],
            ])
            ->add('numAppel', TextType::class, [
                'label' => 'equipement.form.num_call',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => ['placeholder' => 'equipement.form.num_call_placeholder'],
            ])
            ->add('operateur', TextType::class, [
                'label' => 'equipement.form.operator',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => ['placeholder' => 'equipement.form.operator_placeholder'],
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
            ->add('etat', TextType::class, [
                'label' => 'equipement.form.state',
                'translation_domain' => 'equipement',
                'required' => false,
                'attr' => ['placeholder' => 'equipement.form.state_placeholder'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Invitation\InvitationOptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'trim' => false, 'label' => 'Recipient label', 'attr' => ['maxlength' => 128]])
            ->add('maxRequests', TextType::class, ['empty_data' => '', 'label' => 'Lifetime question total', 'attr' => ['inputmode' => 'numeric'], 'help' => '1–10000. Previous admitted questions remain consumed.'])
            ->add('budgetUsd', TextType::class, ['empty_data' => '', 'label' => 'Known-cost threshold (USD)', 'attr' => ['inputmode' => 'decimal'], 'help' => '0–100 USD, up to six decimal places. This is a soft post-request threshold.'])
            ->add('requestsPerMinute', TextType::class, ['empty_data' => '', 'label' => 'Questions per minute', 'attr' => ['inputmode' => 'numeric'], 'help' => '1–60. One active question per invitation also applies.'])
            ->add('expiresInDays', TextType::class, ['required' => !$options['editing'], 'label' => 'Expire in days from now', 'empty_data' => '', 'attr' => ['inputmode' => 'numeric'], 'help' => $options['editing'] ? 'Leave blank to keep expiry. Otherwise 1–365 days from database current time.' : '1–365 days. Default: 30.'])
            ->add('operationId', HiddenType::class)
            ->add('version', HiddenType::class)
            ->add('save', SubmitType::class, ['label' => $options['editing'] ? 'Save changes' : 'Create invitation']);
        foreach (['operationId', 'version'] as $field) {
            $builder->get($field)->addModelTransformer(new CallbackTransformer(static fn ($value) => $value ?? '', static fn ($value) => $value ?? ''));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InvitationOptions::class, 'editing' => false, 'csrf_token_id' => 'invitation']);
        $resolver->setAllowedTypes('editing', 'bool');
    }
}

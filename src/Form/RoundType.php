<?php

namespace App\Form;

use App\Entity\Poll;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Moderator-facing round editor. Edits the round's display copy only — the
 * debate prompt (title), the short chip label, the audience voting question,
 * and the optional "mythbusters" lines. Founder names (the ballot options) are
 * NOT editable here: they come from EventConfig::founders() and stay synced
 * across every round so the cross-round scoreboard tallies one founder.
 *
 * Myths are edited as one-per-line text and (un)packed to the Poll's JSON
 * `myths` array via form events — simpler than a CollectionType.
 */
class RoundType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('roundLabel', TextType::class, [
                'label' => 'Round label (short chip)',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'Debate prompt (the headline on the big screen)',
            ])
            ->add('roundQuestion', TextType::class, [
                'label' => 'Audience question (what voters are scoring)',
                'required' => false,
            ])
            ->add('myths', TextareaType::class, [
                'label' => 'Mythbusters — one per line (optional)',
                'required' => false,
                'mapped' => false,
                'attr' => ['rows' => 4],
            ])
        ;

        // Prefill the unmapped textarea from the stored JSON list.
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $poll = $event->getData();
            $form = $event->getForm();
            if ($poll instanceof Poll && $form->has('myths')) {
                $form->get('myths')->setData(implode("\n", $poll->getMyths()));
            }
        });

        // Pack the textarea back into the JSON list (drop blank lines).
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $poll = $event->getData();
            $form = $event->getForm();
            if (!$poll instanceof Poll || !$form->has('myths')) {
                return;
            }
            $raw = (string) $form->get('myths')->getData();
            $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), static fn (string $l): bool => '' !== $l));
            $poll->setMyths($lines);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Poll::class,
        ]);
    }
}

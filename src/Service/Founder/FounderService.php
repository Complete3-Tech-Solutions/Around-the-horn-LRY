<?php

declare(strict_types=1);

namespace App\Service\Founder;

use App\Entity\Founder;
use App\Event\EventConfig;
use App\Repository\FounderRepository;
use App\Repository\PollRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Founder roster maintenance. The roster is the ballot: each founder's
 * `position` is the poll choice index. Whenever the roster changes we re-sync
 * every round poll's question1..N columns (the denormalised ballot labels +
 * VoteType's option count) so the audience ballot always matches the roster.
 */
class FounderService
{
    /** Schema cap: Poll has question1..question5. */
    public const MAX_FOUNDERS = 5;

    public function __construct(
        private readonly FounderRepository $founderRepository,
        private readonly PollRepository $pollRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventConfig $eventConfig,
    ) {
    }

    public function count(): int
    {
        return \count($this->founderRepository->findOrdered());
    }

    public function canAdd(): bool
    {
        return $this->count() < self::MAX_FOUNDERS;
    }

    /**
     * Seed the editable roster from the EventConfig defaults (idempotent — only
     * runs when the table is empty). Returns the number created.
     */
    public function seedDefaults(): int
    {
        if ([] !== $this->founderRepository->findOrdered()) {
            return 0;
        }

        $created = 0;
        foreach ($this->eventConfig->defaultFounders() as $i => $default) {
            $founder = (new Founder())
                ->setPosition($i + 1)
                ->setName($default['name'])
                ->setCompany($default['sector'])
                ->setCharity($default['charity']);
            $this->entityManager->persist($founder);
            ++$created;
        }
        $this->entityManager->flush();
        $this->syncRoundBallots();

        return $created;
    }

    public function nextPosition(): int
    {
        return $this->founderRepository->maxPosition() + 1;
    }

    /**
     * Close gaps in positions after a delete (1..N), keeping order.
     */
    public function repackPositions(): void
    {
        $pos = 1;
        foreach ($this->founderRepository->findOrdered() as $founder) {
            $founder->setPosition($pos++);
        }
        $this->entityManager->flush();
    }

    /**
     * Re-point every round poll's question1..N at the current roster names so
     * the ballot (VoteType) and choice→founder mapping stay in lockstep.
     */
    public function syncRoundBallots(): void
    {
        $names = array_map(
            static fn (Founder $f): string => (string) $f->getName(),
            $this->founderRepository->findOrdered()
        );

        foreach ($this->pollRepository->findRoundPolls() as $poll) {
            for ($i = 1; $i <= self::MAX_FOUNDERS; ++$i) {
                $setter = 'setQuestion'.$i;
                $poll->{$setter}($names[$i - 1] ?? null);
            }
        }
        $this->entityManager->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Poll;

use App\Entity\Poll;
use App\Repository\PollRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class PollService
{
    public const VOTE_WINDOW_SECONDS = 30;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PollRepository $pollRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * The round currently on stage (moderator went live). Auto-closes once the
     * 30-second voting window ends.
     */
    public function getStagePoll(): ?Poll
    {
        $poll = $this->pollRepository->findOneOnStage();
        if (null === $poll) {
            return null;
        }

        if ($this->autoCloseIfVotingEnded($poll)) {
            return null;
        }

        return $poll;
    }

    /** @deprecated use getStagePoll() or Poll::isVotingOpen() */
    public function getActivePoll(): ?Poll
    {
        return $this->getStagePoll();
    }

    public function checkIfPollIsActive(): bool
    {
        return null !== $this->getStagePoll();
    }

    public function checkIfPollHasVotes(Poll $poll): bool
    {
        return $poll->getVotes()->count() > 0;
    }

    /**
     * Number of round-tagged polls (the live "Around the Horn" rounds).
     */
    public function countRoundPolls(): int
    {
        return \count($this->pollRepository->findRoundPolls());
    }

    public function checkIfPollIsExpired(Poll $poll): bool
    {
        if (!$poll->hasVotingStarted()) {
            return false;
        }

        return $poll->getEndAt() < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Create a new Poll instance.
     */
    public function createPoll(): Poll
    {
        $poll = new Poll();

        $poll
            ->setStartAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setEndAt(new \DateTimeImmutable('+2 minutes', new \DateTimeZone('UTC')))
            ->setShortCode($this->generateShortCode())
        ;

        return $poll;
    }

    /**
     * Persist a Poll instance.
     */
    public function persistPoll(Poll $poll): Poll
    {
        $stagePoll = $this->pollRepository->findOneOnStage();
        if ($stagePoll && $stagePoll->getId() !== $poll->getId()) {
            $poll->setDraft(true);
        }

        $this->entityManager->persist($poll);
        $this->entityManager->flush();

        return $poll;
    }

    public function removePoll(Poll $poll): void
    {
        $this->entityManager->remove($poll);
        $this->entityManager->flush();
    }

    /**
     * After the voting window passes, draft the round so the scoreboard can score it.
     */
    public function autoCloseIfVotingEnded(Poll $poll): bool
    {
        if (!$poll->isOnStage() || !$poll->hasVotingStarted()) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($poll->getEndAt() >= $now) {
            return false;
        }

        $poll->setDraft(true);
        $this->entityManager->flush();
        $this->cache->delete('phone_standings');

        return true;
    }

    private function generateShortCode(): string
    {
        $prefix = base_convert(date('Ymd'), 10, 36);
        $suffix = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 4);

        return $prefix.$suffix;
    }
}

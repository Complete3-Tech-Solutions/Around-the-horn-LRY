<?php

declare(strict_types=1);

namespace App\Service\Scoreboard;

use App\Entity\Poll;
use App\Event\EventConfig;
use App\Repository\PollRepository;
use App\Service\Poll\PollService;

/**
 * Lightweight cumulative tally across the four "Around the Horn" round polls.
 *
 * OpenStreamPoll only models single, independent polls. This service stitches
 * the round polls together: it maps each poll's choice index to a founder (via
 * the fixed ballot order in EventConfig) and awards the round winner a point.
 *
 * Scoring rule (matches the client review reference): a round is "decided" once
 * it is no longer the live/active poll and has at least one vote; the founder(s)
 * with the most votes in that round each earn one point. The active round is
 * shown live but not yet scored, so standings never flip mid-vote.
 */
class ScoreboardService
{
    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly PollService $pollService,
        private readonly EventConfig $eventConfig,
    ) {
    }

    /**
     * @return list<Poll> the round polls, ordered 1..4
     */
    public function roundPolls(): array
    {
        return $this->pollRepository->findRoundPolls();
    }

    public function activePoll(): ?Poll
    {
        return $this->pollService->getActivePoll();
    }

    /**
     * Per-founder live results for a single poll (for the OBS vote bars).
     *
     * @return list<array{founder:array,count:int,pct:float,isLeader:bool}>
     */
    public function liveResults(Poll $poll): array
    {
        $founders = $this->eventConfig->founders();
        $total = $poll->getTotalVotes();

        $counts = [];
        $max = 0;
        foreach ($founders as $i => $founder) {
            $count = $poll->getVotesForChoice($i + 1);
            $counts[$i] = $count;
            $max = max($max, $count);
        }

        $rows = [];
        foreach ($founders as $i => $founder) {
            $count = $counts[$i];
            $rows[] = [
                'founder' => $founder,
                'count' => $count,
                'pct' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
                'isLeader' => $max > 0 && $count === $max,
            ];
        }

        return $rows;
    }

    /**
     * Cumulative standings across decided rounds, sorted by points (ties keep
     * ballot order thanks to PHP 8 stable sort).
     *
     * @return list<array{founder:array,points:int,wins:list<int>}>
     */
    public function standings(): array
    {
        $founders = $this->eventConfig->founders();
        $active = $this->activePoll();
        $points = array_fill(0, \count($founders), 0);
        $wins = array_fill(0, \count($founders), []);

        foreach ($this->roundPolls() as $poll) {
            if (!$this->isRoundDecided($poll, $active)) {
                continue;
            }

            $counts = [];
            $max = 0;
            foreach ($founders as $i => $founder) {
                $count = $poll->getVotesForChoice($i + 1);
                $counts[$i] = $count;
                $max = max($max, $count);
            }
            if ($max <= 0) {
                continue;
            }

            foreach ($founders as $i => $founder) {
                if ($counts[$i] === $max) {
                    ++$points[$i];
                    $wins[$i][] = $poll->getRoundNumber();
                }
            }
        }

        $rows = [];
        foreach ($founders as $i => $founder) {
            $rows[] = [
                'founder' => $founder,
                'points' => $points[$i],
                'wins' => $wins[$i],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);

        return $rows;
    }

    public function isRoundDecided(Poll $poll, ?Poll $active): bool
    {
        if (null !== $active && $active->getId() === $poll->getId()) {
            return false; // live round — provisional, not scored yet
        }

        return $poll->getTotalVotes() > 0;
    }

    public function decidedRoundCount(): int
    {
        $active = $this->activePoll();
        $count = 0;
        foreach ($this->roundPolls() as $poll) {
            if ($this->isRoundDecided($poll, $active)) {
                ++$count;
            }
        }

        return $count;
    }

    public function totalRoundCount(): int
    {
        return \count($this->eventConfig->rounds());
    }

    /**
     * Overall audience champion from the standings (null until at least one
     * round has been decided).
     *
     * @return array{founder:array,points:int,tie:bool}|null
     */
    public function champion(): ?array
    {
        $standings = $this->standings();
        if ([] === $standings) {
            return null;
        }

        $top = $standings[0];
        if ($top['points'] <= 0) {
            return null;
        }

        $tie = isset($standings[1]) && $standings[1]['points'] === $top['points'];

        return [
            'founder' => $top['founder'],
            'points' => $top['points'],
            'tie' => $tie,
        ];
    }

    /**
     * EventConfig round metadata for the active poll, or null if none is live.
     *
     * @return array{number:int,key:string,label:string,title:string,question:string,myths:list<string>}|null
     */
    public function currentRound(): ?array
    {
        $active = $this->activePoll();
        if (null === $active || null === $active->getRoundNumber()) {
            return null;
        }

        return $this->eventConfig->round($active->getRoundNumber());
    }
}

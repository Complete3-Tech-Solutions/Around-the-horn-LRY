<?php

declare(strict_types=1);

namespace App\Service\Scoreboard;

use App\Entity\Poll;
use App\Event\EventConfig;
use App\Repository\PollRepository;
use App\Service\Poll\PollService;

/**
 * Lightweight cumulative tally across the "Around the Horn" round polls.
 *
 * OpenStreamPoll only models single, independent polls. This service stitches
 * the round polls together: it maps each poll's choice index to a founder (via
 * the fixed ballot order in EventConfig) and awards the round winner a point.
 *
 * Scoring rule: a round is "decided" once it is no longer the live/active poll
 * and has at least one vote; the founder(s) with the most votes in that round
 * each earn one point (rounds won — not cumulative raw vote totals). The active
 * round is shown live but not yet scored, so standings never flip mid-vote.
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
     * @return list<Poll> the round polls, ordered by round number
     */
    public function roundPolls(): array
    {
        return $this->pollRepository->findRoundPolls();
    }

    /**
     * Live round metadata (from the DB), ordered by round number — used by the
     * /obs ribbon and the audience standby page to enumerate the rounds.
     *
     * @return list<array{number:int,key:string,label:string,title:string,question:string,myths:list<string>}>
     */
    public function roundMetas(): array
    {
        $metas = [];
        foreach ($this->roundPolls() as $poll) {
            if (null !== ($meta = $poll->getRoundMeta())) {
                $metas[] = $meta;
            }
        }

        return $metas;
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
     * Cumulative standings across decided rounds (points = rounds won), sorted
     * by points (ties keep ballot order thanks to PHP 8 stable sort).
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
        return \count($this->roundPolls());
    }

    /**
     * Overall audience champion(s) from the standings (null until at least one
     * round has been decided). Points = rounds won, not raw vote totals.
     *
     * @return array{founders:list<array>,points:int,tie:bool}|null
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

        $topPoints = $top['points'];
        $founders = [];
        foreach ($standings as $row) {
            if ($row['points'] === $topPoints) {
                $founders[] = $row['founder'];
            } else {
                break;
            }
        }

        return [
            'founders' => $founders,
            'points' => $topPoints,
            'tie' => \count($founders) > 1,
        ];
    }

    /**
     * The most recently decided round poll (rounds run in order), or null
     * before any round closes — drives the between-rounds recap.
     */
    public function lastDecidedPoll(): ?Poll
    {
        $active = $this->activePoll();
        $last = null;
        foreach ($this->roundPolls() as $poll) {
            if ($this->isRoundDecided($poll, $active)) {
                $last = $poll;
            }
        }

        return $last;
    }

    /**
     * Founder(s) with the most votes in a poll — several when tied.
     *
     * @return list<array{key:string,name:string,sector:string,initials:string,headshot:string,charity:string,color:string}>
     */
    public function roundWinners(Poll $poll): array
    {
        $winners = [];
        foreach ($this->liveResults($poll) as $row) {
            if ($row['isLeader'] && $row['count'] > 0) {
                $winners[] = $row['founder'];
            }
        }

        return $winners;
    }

    /**
     * EventConfig round metadata for the active poll, or null if none is live.
     *
     * @return array{number:int,key:string,label:string,title:string,question:string,myths:list<string>}|null
     */
    public function currentRound(): ?array
    {
        $active = $this->activePoll();
        if (null === $active) {
            return null;
        }

        return $active->getRoundMeta();
    }
}

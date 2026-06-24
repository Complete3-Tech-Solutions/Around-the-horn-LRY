<?php

namespace App\Entity;

use App\Repository\PollRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PollRepository::class)]
class Poll
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $shortCode = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endAt = null;

    /**
     * @var Collection<int, Vote>
     */
    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'poll', orphanRemoval: true)]
    private Collection $votes;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $question1 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $question2 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $question3 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $question4 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $question5 = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isDraft = null;

    /**
     * 1..N for the "Around the Horn" rounds; null for ad-hoc polls.
     * Tags a poll as a round and lets the scoreboard tally a founder across
     * all rounds. Ordering/identity of the round.
     */
    #[ORM\Column(nullable: true)]
    private ?int $roundNumber = null;

    /**
     * Round metadata, moved out of EventConfig so the moderator can add/edit
     * rounds live from /admin. Null on ad-hoc (non-round) polls.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $roundLabel = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $roundQuestion = null;

    /**
     * The "mythbusters" lines for the round (Round 3 in the default seed).
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $myths = null;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * The one question shown on /obs and the audience ballot — audience
     * question when set, otherwise the debate headline.
     */
    public function getVotingPrompt(): string
    {
        if ($this->roundQuestion) {
            return $this->roundQuestion;
        }

        return $this->title ?? 'Who made the strongest case this round?';
    }

    public function getShortCode(): ?string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): static
    {
        $this->shortCode = $shortCode;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    /** Round is on the LED wall / phones (moderator went live) but voting may not be open yet. */
    public function isOnStage(): bool
    {
        return false === $this->isDraft;
    }

    /** Moderator opened voting — warmup and/or live window (not the go-live placeholder). */
    public function isVotingPhase(): bool
    {
        if (!$this->isOnStage()) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->startAt < $now->modify('+1 year');
    }

    /** Three-second buffer after Open vote before the countdown begins. */
    public function isVotingScheduled(): bool
    {
        if (!$this->isVotingPhase()) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->startAt > $now;
    }

    /** Audience can submit votes right now (30-second window after warmup). */
    public function isVotingOpen(): bool
    {
        if (!$this->isOnStage()) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->startAt <= $now && $this->endAt >= $now;
    }

    /** Voting was opened at least once for this on-stage round (window may have ended). */
    public function hasVotingStarted(): bool
    {
        if (!$this->isOnStage()) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->startAt <= $now;
    }

    /**
     * @return Collection<int, Vote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(Vote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setPoll($this);
        }

        return $this;
    }

    public function removeVote(Vote $vote): static
    {
        if ($this->votes->removeElement($vote)) {
            // set the owning side to null (unless already changed)
            if ($vote->getPoll() === $this) {
                $vote->setPoll(null);
            }
        }

        return $this;
    }

    public function getQuestion1(): ?string
    {
        return $this->question1;
    }

    public function setQuestion1(?string $question1): static
    {
        $this->question1 = $question1;

        return $this;
    }

    public function getQuestion2(): ?string
    {
        return $this->question2;
    }

    public function setQuestion2(?string $question2): static
    {
        $this->question2 = $question2;

        return $this;
    }

    public function getQuestion3(): ?string
    {
        return $this->question3;
    }

    public function setQuestion3(?string $question3): static
    {
        $this->question3 = $question3;

        return $this;
    }

    public function getQuestion4(): ?string
    {
        return $this->question4;
    }

    public function setQuestion4(?string $question4): static
    {
        $this->question4 = $question4;

        return $this;
    }

    public function getQuestion5(): ?string
    {
        return $this->question5;
    }

    public function setQuestion5(?string $question5): static
    {
        $this->question5 = $question5;

        return $this;
    }

    public function getVotesForChoice(int $choice): int
    {
        return $this->votes
            ->filter(fn (Vote $vote) => $vote->getChoice() === $choice)
            ->count();
    }

    public function getTotalVotes(): int
    {
        return $this->votes->count();
    }

    public function isDraft(): ?bool
    {
        return $this->isDraft;
    }

    public function setDraft(?bool $isDraft): static
    {
        $this->isDraft = $isDraft;

        return $this;
    }

    public function getRoundNumber(): ?int
    {
        return $this->roundNumber;
    }

    public function setRoundNumber(?int $roundNumber): static
    {
        $this->roundNumber = $roundNumber;

        return $this;
    }

    public function getRoundLabel(): ?string
    {
        return $this->roundLabel;
    }

    public function setRoundLabel(?string $roundLabel): static
    {
        $this->roundLabel = $roundLabel;

        return $this;
    }

    public function getRoundQuestion(): ?string
    {
        return $this->roundQuestion;
    }

    public function setRoundQuestion(?string $roundQuestion): static
    {
        $this->roundQuestion = $roundQuestion;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getMyths(): array
    {
        return $this->myths ?? [];
    }

    /**
     * @param list<string>|null $myths
     */
    public function setMyths(?array $myths): static
    {
        $this->myths = ([] === $myths) ? null : $myths;

        return $this;
    }

    /**
     * Round metadata in the shape the /obs + /poll templates consume. Mirrors
     * the array EventConfig::round() used to return, but sourced from this
     * poll's own columns so the moderator can edit it live. The short chip
     * label falls back to "Round N"; the debate headline stays in `title`.
     *
     * @return array{number:int,key:string,label:string,title:string,question:string,myths:list<string>}|null
     */
    public function getRoundMeta(): ?array
    {
        if (null === $this->roundNumber) {
            return null;
        }

        return [
            'number' => $this->roundNumber,
            'key' => 'Round '.$this->roundNumber,
            'label' => $this->roundLabel ?: ('Round '.$this->roundNumber),
            'title' => $this->title ?? '',
            'question' => $this->getVotingPrompt(),
            'myths' => $this->getMyths(),
        ];
    }

    /**
     * The winning choice index (1-based) for this poll, or null on a tie / no votes.
     */
    public function getWinningChoice(): ?int
    {
        $best = null;
        $bestCount = -1;
        $tie = false;

        for ($i = 1; $i <= 5; ++$i) {
            $getter = 'getQuestion'.$i;
            if (null === $this->$getter()) {
                continue;
            }
            $count = $this->getVotesForChoice($i);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $i;
                $tie = false;
            } elseif ($count === $bestCount) {
                $tie = true;
            }
        }

        if (null === $best || 0 === $bestCount) {
            return null; // no votes yet
        }

        return $tie ? null : $best;
    }
}

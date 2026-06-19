<?php

namespace App\Entity;

use App\Repository\FounderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A votable founder. Moved out of EventConfig so the moderator can edit the
 * roster (name, company, charity, headshot) live from /admin.
 *
 * `position` is the 1-based ballot order: it maps to the poll choice index
 * (choice 1 => position 1) so the cross-round scoreboard tallies one founder
 * across every round. The headshot is stored as base64 in the DB (served,
 * cached, via FounderController) so it survives image rebuilds with no upload
 * volume to manage.
 */
#[ORM\Entity(repositoryClass: FounderRepository::class)]
class Founder
{
    /** Ballot palette by position (Innovate Alabama broadcast look). */
    private const COLORS = ['#2b266d', '#04b2e2', '#9a9a9a', '#0043e8', '#28f2e6'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $position = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** The role / company line shown under the name. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $company = null;

    /** Where the Innovate Alabama donation goes if this founder wins. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $charity = null;

    /** Base64-encoded headshot image (null = use the default SVG). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $headshotData = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $headshotMime = null;

    /** Bumped on every save; used as the headshot cache-busting version. */
    #[ORM\Column]
    private ?int $version = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getCharity(): ?string
    {
        return $this->charity;
    }

    public function setCharity(?string $charity): static
    {
        $this->charity = $charity;

        return $this;
    }

    public function getHeadshotData(): ?string
    {
        return $this->headshotData;
    }

    public function setHeadshotData(?string $headshotData): static
    {
        $this->headshotData = $headshotData;

        return $this;
    }

    public function getHeadshotMime(): ?string
    {
        return $this->headshotMime;
    }

    public function setHeadshotMime(?string $headshotMime): static
    {
        $this->headshotMime = $headshotMime;

        return $this;
    }

    public function hasHeadshot(): bool
    {
        return null !== $this->headshotData && null !== $this->headshotMime;
    }

    public function getVersion(): int
    {
        return $this->version ?? 1;
    }

    public function bumpVersion(): static
    {
        $this->version = ($this->version ?? 0) + 1;

        return $this;
    }

    /** Up-to-two-letter initials derived from the name (avatar fallback). */
    public function getInitials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $words = array_values(array_filter($words));
        if ([] === $words) {
            return '?';
        }
        if (1 === \count($words)) {
            return strtoupper(mb_substr($words[0], 0, 2));
        }

        return strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[\count($words) - 1], 0, 1));
    }

    public function getColor(): string
    {
        $i = (($this->position ?? 1) - 1) % \count(self::COLORS);

        return self::COLORS[$i];
    }
}

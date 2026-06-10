<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Single source of truth for the "Around the Horn" event: the founder roster
 * and the round definitions. Drives the seed command, the /obs display, the
 * scoreboard, and the voting/QR pages so they never drift apart.
 *
 * Founder ballot order is FIXED: index 0 => poll choice 1, index 1 => choice 2,
 * index 2 => choice 3. Every round poll lists the founders in this same order,
 * which is what lets the scoreboard tally a single founder across all rounds.
 *
 * NOTE: names/sectors/charities below are PLACEHOLDERS pulled from the client
 * review reference (around-the-horn.html). Swap in the real values once
 * Innovate Alabama confirms them — this is the only file that needs editing.
 * The program's 4th participant (an out-of-state ecosystem expert) is
 * moderator/panel-side by design and is intentionally NOT on the ballot.
 */
final class EventConfig
{
    public const EVENT_TITLE = 'Around the Horn';
    public const EVENT_SUBTITLE = 'Innovate Alabama · Sloss Tech';
    public const EVENT_TAGLINE = 'Three founders. Four rounds. You score it. The winner\'s charity gets the donation.';

    /** Twig-friendly accessors (this class is exposed as the `event_config` global). */
    public function title(): string
    {
        return self::EVENT_TITLE;
    }

    public function subtitle(): string
    {
        return self::EVENT_SUBTITLE;
    }

    public function tagline(): string
    {
        return self::EVENT_TAGLINE;
    }

    /**
     * @return list<array{key:string,name:string,sector:string,initials:string,headshot:string,charity:string,color:string}>
     */
    public function founders(): array
    {
        return [
            [
                'key' => 'f1',
                'name' => 'Dana Reaves',
                'sector' => 'AgTech / Vertical Farming',
                'initials' => 'DR',
                'headshot' => '/img/founders/f1.svg',
                'charity' => 'Alabama Farmers Feeding Families',
                'color' => '#0043e8', // brand blue
            ],
            [
                'key' => 'f2',
                'name' => 'Marcus Hale',
                'sector' => 'Advanced Manufacturing',
                'initials' => 'MH',
                'headshot' => '/img/founders/f2.svg',
                'charity' => 'Birmingham Skills Foundation',
                'color' => '#00B2E3', // logo cyan
            ],
            [
                'key' => 'f3',
                'name' => 'Priya Nair',
                'sector' => 'Logistics / Rail Tech',
                'initials' => 'PN',
                'headshot' => '/img/founders/f3.svg',
                'charity' => 'Mobile Maritime Education Fund',
                'color' => '#9a9a9a', // neutral gray
            ],
        ];
    }

    /**
     * The four sequential voting rounds. round number === Poll.roundNumber.
     *
     * @return list<array{number:int,key:string,label:string,title:string,question:string,myths:list<string>}>
     */
    public function rounds(): array
    {
        return [
            [
                'number' => 1,
                'key' => 'Round 1',
                'label' => 'Why this industry?',
                'title' => 'Why build in this industry — instead of a trendy one?',
                'question' => 'Who made the strongest case for their industry?',
                'myths' => [],
            ],
            [
                'number' => 2,
                'key' => 'Round 2',
                'label' => 'Why Alabama?',
                'title' => 'Why build this company in Alabama instead of anywhere else?',
                'question' => 'Who made the strongest case for Alabama?',
                'myths' => [],
            ],
            [
                'number' => 3,
                'key' => 'Round 3',
                'label' => 'Mythbusters',
                'title' => 'Myths about being a founder',
                'question' => 'Who busted the founder myths best?',
                'myths' => [
                    'Agriculture is outdated.',
                    "Manufacturing can't scale.",
                    'Logistics innovation is dead.',
                    "Young talent won't work in these industries.",
                ],
            ],
            [
                'number' => 4,
                'key' => 'Round 4',
                'label' => 'The hardest part',
                'title' => 'The hardest part about being a founder',
                'question' => 'Who told the most powerful story of the hardest part?',
                'myths' => [],
            ],
        ];
    }

    /**
     * @return array{number:int,key:string,label:string,title:string,question:string,myths:list<string>}|null
     */
    public function round(int $number): ?array
    {
        foreach ($this->rounds() as $round) {
            if ($round['number'] === $number) {
                return $round;
            }
        }

        return null;
    }

    /**
     * Founder for a given 1-based poll choice index.
     *
     * @return array{key:string,name:string,sector:string,initials:string,headshot:string,charity:string,color:string}|null
     */
    public function founderForChoice(int $choice): ?array
    {
        return $this->founders()[$choice - 1] ?? null;
    }

    public function votableFounderCount(): int
    {
        return \count($this->founders());
    }
}

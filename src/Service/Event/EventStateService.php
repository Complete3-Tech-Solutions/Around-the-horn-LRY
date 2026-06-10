<?php

declare(strict_types=1);

namespace App\Service\Event;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tiny persisted moderator state for the live event, stored as JSON on the
 * SQLite volume (survives restarts, needs no schema).
 *
 *   screen : which /obs screen to show — 'auto' (default), 'winner', 'intro'
 *   theme  : the daisyUI UI theme for admin + audience pages —
 *            'innovate' (light, default) or 'innovate-dark' (dark)
 */
class EventStateService
{
    private const ALLOWED_SCREENS = ['auto', 'winner', 'intro'];
    private const ALLOWED_THEMES = ['innovate', 'innovate-dark'];

    private string $file;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ) {
        $this->file = $projectDir.'/var/sqlite/event-state.json';
    }

    public function getScreen(): string
    {
        $screen = $this->read()['screen'] ?? 'auto';

        return \in_array($screen, self::ALLOWED_SCREENS, true) ? $screen : 'auto';
    }

    public function setScreen(string $screen): void
    {
        if (!\in_array($screen, self::ALLOWED_SCREENS, true)) {
            $screen = 'auto';
        }
        $this->merge(['screen' => $screen]);
    }

    public function getTheme(): string
    {
        $theme = $this->read()['theme'] ?? 'innovate';

        return \in_array($theme, self::ALLOWED_THEMES, true) ? $theme : 'innovate';
    }

    public function setTheme(string $theme): void
    {
        if (!\in_array($theme, self::ALLOWED_THEMES, true)) {
            $theme = 'innovate';
        }
        $this->merge(['theme' => $theme]);
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->file), true);

        return \is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function merge(array $values): void
    {
        $data = array_merge($this->read(), $values);

        if (!is_dir(\dirname($this->file))) {
            @mkdir(\dirname($this->file), 0775, true);
        }

        file_put_contents($this->file, json_encode($data));
    }
}

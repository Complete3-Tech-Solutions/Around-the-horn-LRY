<?php

namespace App\Controller;

use App\Entity\Poll;
use App\Enum\FlashTypeEnum;
use App\Repository\PollRepository;
use App\Service\Event\EventStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Moderator-facing round control for the live event. All routes sit under
 * /admin, so the security firewall (ROLE_USER) protects them — audience members
 * cannot reach these write endpoints. Each action is POST + CSRF-checked.
 *
 * Activating a round enforces the "exactly one live poll" invariant that the
 * rest of the app (findOneActive) depends on, and opens a generous window so a
 * round never expires mid-conversation; the moderator closes it explicitly.
 */
#[Route('/admin', name: 'app_admin_')]
class RoundController extends AbstractController
{
    private const LIVE_WINDOW_MINUTES = 60;

    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventStateService $eventState,
    ) {
    }

    #[Route('/round/{id}/activate', name: 'round_activate', methods: ['POST'])]
    public function activate(Request $request, Poll $poll): Response
    {
        if (!$this->isCsrfTokenValid('round'.$poll->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_index');
        }

        // Enforce a single live poll: draft every other non-draft poll first.
        foreach ($this->pollRepository->findAll() as $other) {
            if ($other->getId() !== $poll->getId() && true !== $other->isDraft()) {
                $other->setDraft(true);
            }
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $poll
            ->setStartAt($now)
            ->setEndAt($now->modify('+'.self::LIVE_WINDOW_MINUTES.' minutes'))
            ->setDraft(false);

        // Going live always resumes the auto screen (in case the winner was up).
        $this->eventState->setScreen('auto');
        $this->entityManager->flush();

        $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('"%s" is now LIVE.', $poll->getTitle()));

        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/round/{id}/close', name: 'round_close', methods: ['POST'])]
    public function close(Request $request, Poll $poll): Response
    {
        if (!$this->isCsrfTokenValid('round'.$poll->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_index');
        }

        // Drafting removes it from "active"; the scoreboard then scores the round.
        $poll->setDraft(true);
        $this->entityManager->flush();

        $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('"%s" closed and scored.', $poll->getTitle()));

        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/event/screen/{screen}', name: 'event_screen', methods: ['POST'], requirements: ['screen' => 'auto|winner|intro'])]
    public function screen(Request $request, string $screen): Response
    {
        if (!$this->isCsrfTokenValid('event_screen', (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_index');
        }

        $this->eventState->setScreen($screen);
        $this->addFlash(FlashTypeEnum::SUCCESS->value, 'OBS screen set to "'.$screen.'".');

        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/event/theme/{theme}', name: 'event_theme', methods: ['POST'], requirements: ['theme' => 'innovate|innovate-dark'])]
    public function theme(Request $request, string $theme): Response
    {
        if (!$this->isCsrfTokenValid('event_theme', (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_index');
        }

        $this->eventState->setTheme($theme);
        $this->addFlash(FlashTypeEnum::SUCCESS->value, 'UI theme set to '.('innovate-dark' === $theme ? 'dark' : 'light').'.');

        return $this->redirectToRoute('app_admin_index');
    }
}

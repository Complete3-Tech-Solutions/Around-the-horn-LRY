<?php

namespace App\Controller;

use App\Entity\Poll;
use App\Enum\FlashTypeEnum;
use App\Event\EventConfig;
use App\Form\RoundType;
use App\Repository\PollRepository;
use App\Repository\VoteRepository;
use App\Service\Poll\PollService;
use App\Service\Event\EventStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

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
    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly VoteRepository $voteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventStateService $eventState,
        private readonly EventConfig $eventConfig,
        private readonly CacheInterface $cache,
        private readonly PollService $pollService,
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
        // On stage, but voting stays closed until the moderator opens it.
        $closed = $now->modify('+10 years');
        $poll
            ->setStartAt($closed)
            ->setEndAt($closed)
            ->setDraft(false);

        // Going live always resumes the auto screen (in case the winner was up).
        $this->eventState->setScreen('auto');
        $this->entityManager->flush();

        $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('"%s" is on stage — open voting when ready.', $poll->getTitle()));

        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/round/{id}/open-vote', name: 'round_open_vote', methods: ['POST'])]
    public function openVote(Request $request, Poll $poll): Response
    {
        if (!$this->isCsrfTokenValid('round'.$poll->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_index');
        }

        $stage = $this->pollRepository->findOneOnStage();
        if (null === $stage || $stage->getId() !== $poll->getId()) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Go live on this round before opening voting.');

            return $this->redirectToRoute('app_admin_index');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $poll
            ->setStartAt($now->modify('+'.PollService::VOTE_DELAY_SECONDS.' seconds'))
            ->setEndAt($now->modify('+'.(PollService::VOTE_DELAY_SECONDS + PollService::VOTE_WINDOW_SECONDS).' seconds'));

        $this->entityManager->flush();
        $this->cache->delete('phone_standings');

        $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf(
            'Voting opens in %d seconds, then %d seconds to vote.',
            PollService::VOTE_DELAY_SECONDS,
            PollService::VOTE_WINDOW_SECONDS,
        ));

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
        $this->cache->delete('phone_standings');

        $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('"%s" closed and scored.', $poll->getTitle()));

        return $this->redirectToRoute('app_admin_index');
    }

    /**
     * Reset the event for a fresh run: wipe every round's votes, draft all
     * rounds, and put the OBS display back on the intro screen. The rounds
     * themselves (and their edited copy) are kept.
     */
    #[Route('/event/reset', name: 'event_reset', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('event_reset', (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_index');
        }

        $deleted = $this->voteRepository->deleteForRoundPolls();

        foreach ($this->pollRepository->findRoundPolls() as $poll) {
            $poll->setDraft(true);
        }
        $this->entityManager->flush();

        // Back to pre-show: intro screen, and drop the cached phone scoreboard.
        $this->eventState->setScreen('intro');
        $this->cache->delete('phone_standings');

        $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('New game ready — %d vote(s) cleared and all rounds reset to draft.', $deleted));

        return $this->redirectToRoute('app_admin_index');
    }

    /**
     * Add a new round: next free round number, founders pre-filled as the
     * ballot, sensible placeholder copy, left as a draft — then jump to edit.
     */
    #[Route('/round/new', name: 'round_new', methods: ['POST'])]
    public function newRound(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('round_new', (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_index');
        }

        $next = 1;
        foreach ($this->pollRepository->findRoundPolls() as $existing) {
            $next = max($next, (int) $existing->getRoundNumber() + 1);
        }

        $utc = new \DateTimeZone('UTC');
        $round = new Poll();
        $round
            ->setTitle('Round '.$next.' — set the debate prompt')
            ->setShortCode('round'.$next)
            ->setRoundNumber($next)
            ->setRoundLabel('Round '.$next)
            ->setRoundQuestion('Who made the strongest case this round?')
            ->setMyths([])
            ->setStartAt(new \DateTimeImmutable('now', $utc))
            ->setEndAt(new \DateTimeImmutable('+60 minutes', $utc))
            ->setDraft(true);

        // Founders in fixed ballot order => poll choices 1..N.
        foreach ($this->eventConfig->founders() as $i => $founder) {
            $setter = 'setQuestion'.($i + 1);
            $round->{$setter}($founder['name']);
        }

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('Round %d added — edit its prompt and question.', $next));

        return $this->redirectToRoute('app_admin_round_edit', ['id' => $round->getId()]);
    }

    /**
     * Edit a round's display copy (debate prompt, label, audience question,
     * myths). Founder/ballot options are managed centrally and not editable
     * here. CSRF is the form's stateless `submit` token (Origin/Referer).
     */
    #[Route('/round/{id}/edit', name: 'round_edit', methods: ['GET', 'POST'])]
    public function editRound(Request $request, Poll $poll): Response
    {
        $form = $this->createForm(RoundType::class, $poll);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('Round %s updated.', $poll->getRoundNumber()));

            return $this->redirectToRoute('app_admin_index');
        }

        return $this->render('admin/round_edit.html.twig', [
            'poll' => $poll,
            'form' => $form,
        ]);
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

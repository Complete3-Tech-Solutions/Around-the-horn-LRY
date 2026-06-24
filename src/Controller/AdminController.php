<?php

namespace App\Controller;

use App\Entity\Poll;
use App\Enum\FlashTypeEnum;
use App\Form\PollType;
use App\Repository\PollRepository;
use App\Service\Event\EventStateService;
use App\Service\Poll\PollService;
use App\Service\Scoreboard\ScoreboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'app_admin_')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly PollService $pollService,
        private readonly ScoreboardService $scoreboard,
        private readonly EventStateService $eventState,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $polls = $this->pollRepository->findLatest(10);
        $stage = $this->pollService->getStagePoll();

        return $this->render('admin/index.html.twig', [
            'polls' => $polls,
            'seeded' => \count($this->scoreboard->roundPolls()) > 0,
            ...$this->deckViewData($stage),
        ]);
    }

    /**
     * Live deck fragment — polled by the moderator's control deck every couple
     * of seconds while a round is on stage, so vote counts and the round's
     * voting state (warmup → open → window ended) update without a manual
     * refresh.
     */
    #[Route('/_live', name: 'deck_live')]
    public function deckLive(): Response
    {
        $stage = $this->pollService->getStagePoll();

        return $this->render('admin/_control_live.html.twig', $this->deckViewData($stage));
    }

    /**
     * @return array<string, mixed>
     */
    private function deckViewData(?Poll $stage): array
    {
        $controlRounds = $this->controlRounds($stage);
        $liveRound = null;
        $nextUp = null;
        foreach ($controlRounds as $row) {
            if ($row['isActive']) {
                $liveRound = $row;
            }
            if (null === $nextUp && null !== $row['poll'] && !$row['isActive'] && !$row['isDecided']) {
                $nextUp = $row;
            }
        }

        return [
            'controlRounds' => $controlRounds,
            'activePoll' => $stage,
            'liveRound' => $liveRound,
            'nextUp' => $nextUp,
            'totalRounds' => \count($controlRounds),
            'standings' => $this->scoreboard->standings(),
            'eventScreen' => $this->eventState->getScreen(),
        ];
    }

    /**
     * Build the per-round view rows for the control deck. Shared by the full
     * page and the live-poll fragment so both show identical state.
     *
     * @return list<array<string,mixed>>
     */
    private function controlRounds(?Poll $stage): array
    {
        // The rounds are DB-driven (moderator can add/edit/delete them), so
        // enumerate the round polls directly rather than the config seed.
        $rows = [];
        foreach ($this->scoreboard->roundPolls() as $poll) {
            $meta = $poll->getRoundMeta();
            $onStage = null !== $stage && $poll->getId() === $stage->getId();
            $rows[] = [
                'number' => $meta['number'],
                'label' => $meta['label'],
                'title' => $meta['title'],
                'poll' => $poll,
                'isActive' => $onStage,
                'isVotingOpen' => $onStage && $poll->isVotingOpen(),
                'isVotingScheduled' => $onStage && $poll->isVotingScheduled(),
                'hasVotingStarted' => $onStage && $poll->hasVotingStarted(),
                'isDecided' => $this->scoreboard->isRoundDecided($poll, $stage),
                'votes' => $poll->getTotalVotes(),
            ];
        }

        return $rows;
    }

    #[Route('/show/{id}', name: 'show')]
    public function show(?Poll $poll = null): Response
    {
        if (null === $poll) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'This poll does not exist.');

            return $this->redirectToRoute('app_admin_index');
        }

        return $this->render('admin/show.html.twig', [
            'poll' => $poll,
        ]);
    }

    #[Route('/create', name: 'create')]
    public function create(Request $request): Response
    {
        $poll = $this->pollService->createPoll();

        if ($this->pollService->checkIfPollIsActive()) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Active poll exists. Status set to draft.');
            $poll->setDraft(true);
        }

        return $this->handleForm($request, $poll, 'admin/create.html.twig');
    }

    #[Route('/edit/{id}', name: 'edit')]
    public function edit(Request $request, ?Poll $poll = null): Response
    {
        if (null === $poll) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'This poll does not exist.');

            return $this->redirectToRoute('app_admin_index');
        }

        if ($this->pollService->checkIfPollHasVotes($poll)) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'This poll has votes and cannot be edited.');

            return $this->redirectToRoute('app_admin_show', ['id' => $poll->getId()]);
        }

        return $this->handleForm($request, $poll, 'admin/create.html.twig');
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Poll $poll): Response
    {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$poll->getId(), (string) $token)) {
            $this->pollService->removePoll($poll);
        }

        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/_results/{id}', name: 'results')]
    public function results(Poll $poll): Response
    {
        return $this->render('admin/_results.html.twig', [
            'poll' => $poll,
        ]);
    }

    private function handleForm(Request $request, Poll $poll, string $template): Response
    {
        $form = $this->createForm(PollType::class, $poll);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->pollService->persistPoll($poll);

            return $this->redirectToRoute('app_admin_show', ['id' => $poll->getId()]);
        }

        return $this->render($template, [
            'form' => $form,
        ]);
    }
}

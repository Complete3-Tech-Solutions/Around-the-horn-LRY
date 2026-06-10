<?php

namespace App\Controller;

use App\Entity\Poll;
use App\Enum\FlashTypeEnum;
use App\Event\EventConfig;
use App\Form\VoteType;
use App\Service\Poll\PollService;
use App\Service\Scoreboard\ScoreboardService;
use App\Service\Security\VoteRateLimiter;
use App\Service\Visitor\VisitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Single audience entry point — one QR for the whole show. Always routes to
 * whichever round is currently live; auto-refreshes between rounds.
 */
class VoteController extends AbstractController
{
    public function __construct(
        private readonly VisitorService $visitorService,
        private readonly PollService $pollService,
        private readonly VoteRateLimiter $rateLimiter,
        private readonly EventConfig $eventConfig,
        private readonly ScoreboardService $scoreboard,
        private readonly CacheInterface $cache,
    ) {
    }

    #[Route('/vote', name: 'app_vote_live', methods: ['GET', 'POST'])]
    public function live(Request $request): Response
    {
        [$voterId, $cookie] = $this->visitorService->resolveVoterId($request);

        $poll = $this->pollService->getActivePoll();
        if (null === $poll) {
            $response = $this->renderWaiting();
        } else {
            $response = $this->handle($request, $poll, $voterId, true);
        }

        if (null !== $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    private function handle(Request $request, Poll $poll, string $voterId, bool $liveEntry): Response
    {
        if ($poll->isDraft()) {
            return $this->renderWaiting();
        }

        if ($this->pollService->checkIfPollIsExpired($poll)) {
            return $this->renderWaiting();
        }

        if (null !== ($existing = $this->visitorService->findVisitorVote($voterId, $poll))) {
            return $this->render('poll/success.html.twig', [
                'title' => $poll->getTitle(),
                'auto_refresh' => $liveEntry,
                'round' => $poll->getRoundNumber() ? $this->eventConfig->round($poll->getRoundNumber()) : null,
                'picked' => $poll->getRoundNumber() ? $this->eventConfig->founderForChoice($existing->getChoice()) : null,
            ]);
        }

        $vote = $this->visitorService->createVote($poll, $voterId);
        $form = $this->createForm(VoteType::class, $vote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ip = $request->getClientIp() ?? 'noip';

            if (!$this->rateLimiter->allow('voter:'.$voterId, 8, 30)
                || !$this->rateLimiter->allow('ip:'.$ip, 200, 60)) {
                $this->addFlash(FlashTypeEnum::ERROR->value, 'Too many attempts — please wait a moment and try again.');
            } else {
                if (!$this->visitorService->checkIfVisitorHasVoted($voterId, $poll)) {
                    $this->visitorService->saveVote($vote);
                }

                return $this->redirectToRoute('app_vote_live');
            }
        }

        return $this->render('poll/show.html.twig', [
            'poll' => $poll,
            'voterId' => $voterId,
            'form' => $form,
        ]);
    }

    /**
     * Between-rounds screen with the cross-round scoreboard. The standings are
     * cached briefly because every idle phone re-polls /vote every few seconds —
     * the tally must not run per request.
     */
    private function renderWaiting(): Response
    {
        $board = $this->cache->get('phone_standings', function (ItemInterface $item): array {
            $item->expiresAfter(3);

            $standings = $this->scoreboard->standings();

            $decidedRounds = [];
            foreach ($standings as $row) {
                $decidedRounds = array_merge($decidedRounds, $row['wins']);
            }
            $decidedRounds = array_values(array_unique($decidedRounds));
            sort($decidedRounds);

            return ['standings' => $standings, 'decided_rounds' => $decidedRounds];
        });

        return $this->render('poll/waiting.html.twig', [
            'standings' => $board['standings'],
            'decided_rounds' => $board['decided_rounds'],
            'total_rounds' => $this->scoreboard->totalRoundCount(),
        ]);
    }
}

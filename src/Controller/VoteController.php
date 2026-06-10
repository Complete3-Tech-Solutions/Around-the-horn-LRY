<?php

namespace App\Controller;

use App\Entity\Poll;
use App\Enum\FlashTypeEnum;
use App\Form\VoteType;
use App\Service\Poll\PollService;
use App\Service\Security\VoteRateLimiter;
use App\Service\Visitor\VisitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    ) {
    }

    #[Route('/vote', name: 'app_vote_live', methods: ['GET', 'POST'])]
    public function live(Request $request): Response
    {
        [$voterId, $cookie] = $this->visitorService->resolveVoterId($request);

        $poll = $this->pollService->getActivePoll();
        if (null === $poll) {
            $response = $this->render('poll/waiting.html.twig');
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
            return $this->render('poll/waiting.html.twig');
        }

        if ($this->pollService->checkIfPollIsExpired($poll)) {
            return $this->render('poll/waiting.html.twig');
        }

        if ($this->visitorService->checkIfVisitorHasVoted($voterId, $poll)) {
            return $this->render('poll/success.html.twig', [
                'title' => $poll->getTitle(),
                'auto_refresh' => $liveEntry,
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
}

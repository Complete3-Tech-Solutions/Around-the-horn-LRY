<?php

namespace App\Controller;

use App\Entity\Poll;
use App\Enum\FlashTypeEnum;
use App\Event\EventConfig;
use App\Form\VoteType;
use App\Service\Poll\PollService;
use App\Service\Security\VoteRateLimiter;
use App\Service\Visitor\VisitorService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/poll', name: 'app_poll_')]
class PollController extends AbstractController
{
    public function __construct(
        private readonly VisitorService $visitorService,
        private readonly PollService $pollService,
        private readonly VoteRateLimiter $rateLimiter,
        private readonly EventConfig $eventConfig,
    ) {
    }

    #[Route('/{shortCode}', name: 'show')]
    public function show(
        Request $request,
        #[MapEntity(mapping: ['shortCode' => 'shortCode'])]
        ?Poll $poll = null,
    ): Response {
        // Per-device identity via first-party cookie (set on every response).
        [$voterId, $cookie] = $this->visitorService->resolveVoterId($request);

        $response = $this->handle($request, $poll, $voterId);

        if (null !== $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    private function handle(Request $request, ?Poll $poll, string $voterId): Response
    {
        if (null === $poll) {
            return $this->render('poll/error.html.twig', ['message' => 'This poll does not exist.']);
        }

        if ($poll->isDraft()) {
            return $this->render('poll/error.html.twig', ['message' => 'This poll is not available.']);
        }

        if ($this->pollService->checkIfPollIsExpired($poll)) {
            return $this->render('poll/error.html.twig', ['message' => 'This poll is no longer available.']);
        }

        // Already voted from this device → thank-you screen (with the pick).
        if (null !== ($existing = $this->visitorService->findVisitorVote($voterId, $poll))) {
            return $this->render('poll/success.html.twig', [
                'title' => $poll->getTitle(),
                'round' => $poll->getRoundMeta(),
                'picked' => $poll->getRoundNumber() ? $this->eventConfig->founderForChoice($existing->getChoice()) : null,
            ]);
        }

        $vote = $this->visitorService->createVote($poll, $voterId);
        $form = $this->createForm(VoteType::class, $vote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ip = $request->getClientIp() ?? 'noip';

            // Throttle: tight per-device cap, generous per-IP backstop (50-75
            // legitimate voters can share one venue NAT, so the IP cap is high).
            if (!$this->rateLimiter->allow('voter:'.$voterId, 8, 30)
                || !$this->rateLimiter->allow('ip:'.$ip, 200, 60)) {
                $this->addFlash(FlashTypeEnum::ERROR->value, 'Too many attempts — please wait a moment and try again.');
            } else {
                // Re-check right before persisting to narrow the double-submit window.
                if (!$this->visitorService->checkIfVisitorHasVoted($voterId, $poll)) {
                    $this->visitorService->saveVote($vote);
                }

                return $this->redirectToRoute('app_poll_show', ['shortCode' => $poll->getShortCode()]);
            }
        }

        return $this->render('poll/show.html.twig', [
            'poll' => $poll,
            'voterId' => $voterId,
            'form' => $form,
            'total_rounds' => $this->pollService->countRoundPolls(),
        ]);
    }
}

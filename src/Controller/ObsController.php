<?php

namespace App\Controller;

use App\Event\EventConfig;
use App\Service\Event\EventStateService;
use App\Service\Poll\PollService;
use App\Service\Qr\QrService;
use App\Service\Scoreboard\ScoreboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Browser-source endpoints for OBS / the LED wall. All read-only and public so
 * the production switcher can drop the URLs in without auth.
 *
 *   /obs            — the live stage shell (persistent logo + 2s-refreshing stage)
 *   /obs/results    — the stage fragment (round, founders+bars, scoreboard, winner)
 *   /obs/qr         — the auto-updating QR browser source
 *   /obs/qr/results — the QR fragment (regenerates for the active round)
 *
 * Background modes (composite over a video feed): ?bg=transparent (default),
 * ?bg=white, ?bg=chroma (green key), ?bg=stage (opaque branded fill).
 * Force a screen with ?screen=winner|intro|auto (default auto / moderator flag).
 */
class ObsController extends AbstractController
{
    private const BG_MODES = ['transparent', 'white', 'chroma', 'stage'];

    public function __construct(
        private readonly PollService $pollService,
        private readonly QrService $qrService,
        private readonly ScoreboardService $scoreboard,
        private readonly EventStateService $eventState,
        private readonly EventConfig $eventConfig,
    ) {
    }

    #[Route('/obs', name: 'app_obs')]
    public function index(Request $request): Response
    {
        $dark = 'innovate-dark' === $this->eventState->getTheme();
        $voteUrl = $this->generateUrl('app_vote_live', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('obs/index.html.twig', [
            'bg' => $this->resolveBg($request, $dark),
            'dark' => $dark,
            'screen' => $request->query->get('screen'),
            'qr' => $this->qrService->generateQrCode($voteUrl),
        ]);
    }

    #[Route('/obs/results', name: 'app_obs_results')]
    public function results(Request $request): Response
    {
        $active = $this->scoreboard->activePoll();
        $decided = $this->scoreboard->decidedRoundCount();
        $total = $this->scoreboard->totalRoundCount();

        // Screen selection: explicit ?screen= wins, else the persisted moderator
        // flag, else auto-derive from poll/scoreboard state.
        $pref = $request->query->get('screen') ?: $this->eventState->getScreen();
        if ('winner' === $pref) {
            $screen = 'winner';
        } elseif ('intro' === $pref) {
            $screen = 'intro';
        } elseif (null !== $active) {
            $screen = 'live';
        } elseif ($decided > 0) {
            $screen = 'standby';
        } else {
            $screen = 'intro';
        }

        $voteUrl = $this->generateUrl('app_vote_live', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $qr = $this->qrService->generateQrCode($voteUrl);

        // Between-rounds recap: who took the last round, what's coming next.
        $roundStates = $this->buildRoundStates($active);
        $lastDecided = $this->scoreboard->lastDecidedPoll();
        $nextRound = null;
        foreach ($roundStates as $rs) {
            if ('pending' === $rs['state'] && null !== $rs['poll']) {
                $nextRound = $rs['poll']->getRoundMeta();
                break;
            }
        }

        return $this->render('obs/_results.html.twig', [
            'screen' => $screen,
            'activePoll' => $active,
            'round' => null !== $active ? $active->getRoundMeta() : null,
            'roundStates' => $roundStates,
            'lastRound' => null !== $lastDecided ? $lastDecided->getRoundMeta() : null,
            'lastWinners' => null !== $lastDecided ? $this->scoreboard->roundWinners($lastDecided) : [],
            'lastRoundResults' => null !== $lastDecided ? $this->scoreboard->liveResults($lastDecided) : [],
            'nextRound' => $nextRound,
            'liveResults' => null !== $active && $active->isVotingOpen() ? $this->scoreboard->liveResults($active) : [],
            'votingOpen' => null !== $active && $active->isVotingOpen(),
            'standings' => $this->scoreboard->standings(),
            'champion' => $this->scoreboard->champion(),
            'decided' => $decided,
            'total' => $total,
            'founders' => $this->eventConfig->founders(),
            'qr' => $qr,
            'eventTitle' => EventConfig::EVENT_TITLE,
            'eventSubtitle' => EventConfig::EVENT_SUBTITLE,
            'eventTagline' => EventConfig::EVENT_TAGLINE,
        ]);
    }

    #[Route('/obs/qr', name: 'app_obs_qr')]
    public function qr(): Response
    {
        return $this->render('obs/qr.html.twig', [
            'dark' => 'innovate-dark' === $this->eventState->getTheme(),
        ]);
    }

    #[Route('/obs/qr/results', name: 'app_obs_qr_results')]
    public function qrResults(): Response
    {
        $poll = $this->pollService->getStagePoll();

        if (null === $poll) {
            return new Response('');
        }

        $url = $this->generateUrl('app_vote_live', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('obs/_qr_results.html.twig', [
            'qrCode' => $this->qrService->generateQrCode($url),
            'poll' => $poll,
        ]);
    }

    private function resolveBg(Request $request, bool $dark): string
    {
        $bg = $request->query->get('bg');
        if (null !== $bg && \in_array($bg, self::BG_MODES, true)) {
            return $bg; // explicit production override (e.g. transparent/chroma)
        }

        // No explicit ?bg= → derive the page background from the moderator theme.
        return $dark ? 'stage' : 'transparent';
    }

    /**
     * Live round progress from the DB round polls (so rounds added from /admin
     * appear in the ribbon and "up next").
     *
     * @return list<array{number:int,label:string,state:string,poll:\App\Entity\Poll}>
     */
    private function buildRoundStates(?\App\Entity\Poll $active): array
    {
        $states = [];
        foreach ($this->scoreboard->roundPolls() as $poll) {
            $meta = $poll->getRoundMeta();
            if (null === $meta) {
                continue;
            }
            $state = 'pending';
            if (null !== $active && $active->getId() === $poll->getId()) {
                $state = 'active';
            } elseif ($this->scoreboard->isRoundDecided($poll, $active)) {
                $state = 'done';
            }
            $states[] = ['number' => $meta['number'], 'label' => $meta['label'], 'state' => $state, 'poll' => $poll];
        }

        return $states;
    }
}

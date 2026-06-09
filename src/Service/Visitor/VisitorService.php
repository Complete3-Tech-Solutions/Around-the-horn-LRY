<?php

declare(strict_types=1);

namespace App\Service\Visitor;

use App\Entity\Poll;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

class VisitorService
{
    public const VOTER_COOKIE = 'osp_vid';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Per-device voter id backed by a first-party cookie. Replaces the old
     * IP+User-Agent fingerprint, which collapsed every phone behind the event's
     * shared WiFi/NAT onto the same id (wrongly blocking legitimate voters) and
     * was trivially spoofable. Each browser now gets its own random id; the
     * one-vote-per-poll check then works per device, not per network.
     *
     * @return array{0: string, 1: Cookie|null} [voterId, cookieToSetOrNull]
     */
    public function resolveVoterId(Request $request): array
    {
        $existing = $request->cookies->get(self::VOTER_COOKIE);
        if (\is_string($existing) && 1 === preg_match('/^[a-f0-9]{32}$/', $existing)) {
            return [$existing, null];
        }

        $id = bin2hex(random_bytes(16));
        $cookie = Cookie::create(self::VOTER_COOKIE, $id)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withSecure($request->isSecure())
            ->withPath('/')
            ->withExpires(new \DateTimeImmutable('+1 day'));

        return [$id, $cookie];
    }

    public function saveVote(Vote $vote): void
    {
        $this->entityManager->persist($vote);
        $this->entityManager->flush();
    }

    public function createVote(Poll $poll, string $voterId): Vote
    {
        $vote = new Vote();
        $vote->setVoterId($voterId);
        $vote->setPoll($poll);
        $vote->setCreatedAt(new \DateTimeImmutable());

        return $vote;
    }

    public function checkIfVisitorHasVoted(string $voterId, Poll $poll): bool
    {
        return $poll->getVotes()->exists(function ($key, $vote) use ($voterId) {
            return $vote->getVoterId() === $voterId;
        });
    }

    public function getClientIdFromRequest(Request $request): string
    {
        $ip = $request->getClientIp();
        $ip = $ip ?: random_bytes(3);
        $ipEncoded = base_convert($ip, 10, 36);

        $userAgent = $request->headers->get('User-Agent');
        $userAgent = $userAgent ?: 'unknown';
        $userAgentEncoded = base_convert($userAgent, 10, 36);

        return $ipEncoded.$userAgentEncoded;
    }
}

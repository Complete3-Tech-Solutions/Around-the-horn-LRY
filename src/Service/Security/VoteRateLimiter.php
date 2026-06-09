<?php

declare(strict_types=1);

namespace App\Service\Security;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Minimal fixed-window rate limiter backed by the application cache pool.
 *
 * Deliberately dependency-free (no symfony/rate-limiter) because this is a
 * pinned, offline fork: adding a Composer package would require regenerating
 * composer.lock, which the locked `composer install` in the Docker build won't
 * do. The app cache pool ships with the framework, so this needs nothing new.
 *
 * Approximate under heavy concurrency (the increment isn't atomic across
 * workers), which is acceptable for throttling a public vote endpoint.
 */
class VoteRateLimiter
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Returns true if this hit is within the limit, false if it should be rejected.
     */
    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        $item = $this->cache->getItem('rl_'.sha1($key));
        /** @var array{count:int,reset:int}|null $data */
        $data = $item->get();
        $now = time();

        if (!\is_array($data) || ($data['reset'] ?? 0) <= $now) {
            $data = ['count' => 0, 'reset' => $now + $windowSeconds];
        }

        ++$data['count'];
        $item->set($data);
        $item->expiresAfter($windowSeconds);
        $this->cache->save($item);

        return $data['count'] <= $limit;
    }
}

<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Platforms\SqlitePlatform;

/**
 * Tunes SQLite for many concurrent voters. WAL lets readers and one writer run
 * at the same time, busy_timeout makes a writer wait briefly instead of
 * throwing "database is locked" under a burst of votes, and synchronous=NORMAL
 * is the safe, fast pairing for WAL. Registered on Doctrine's postConnect event
 * in config/services.yaml.
 */
class SqlitePragmaListener
{
    public function postConnect(ConnectionEventArgs $args): void
    {
        $connection = $args->getConnection();

        if (!$connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return;
        }

        $connection->executeStatement('PRAGMA journal_mode=WAL');
        $connection->executeStatement('PRAGMA busy_timeout=5000');
        $connection->executeStatement('PRAGMA synchronous=NORMAL');
    }
}

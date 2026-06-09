<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Innovate Alabama "Around the Horn" fork: tag polls with their round number
 * so the /obs display and the cross-round scoreboard can group them.
 */
final class Version20260608000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add round_number to poll (Around the Horn round tagging)';
    }

    public function up(Schema $schema): void
    {
        // SQLite supports ADD COLUMN directly (no table rebuild needed).
        $this->addSql('ALTER TABLE poll ADD COLUMN round_number INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP COLUMN round_number');
    }
}

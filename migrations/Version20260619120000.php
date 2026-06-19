<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move "Around the Horn" round metadata (chip label, audience question, myths)
 * out of EventConfig and onto the poll, so the moderator can add/edit rounds
 * live from /admin instead of editing PHP + re-seeding.
 */
final class Version20260619120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add round_label, round_question and myths to poll (dynamic round metadata)';
    }

    public function up(Schema $schema): void
    {
        // SQLite supports ADD COLUMN directly (no table rebuild needed).
        // Doctrine's TEXT/JSON types map to CLOB on the SQLite platform.
        $this->addSql('ALTER TABLE poll ADD COLUMN round_label CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE poll ADD COLUMN round_question CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE poll ADD COLUMN myths CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP COLUMN round_label');
        $this->addSql('ALTER TABLE poll DROP COLUMN round_question');
        $this->addSql('ALTER TABLE poll DROP COLUMN myths');
    }
}

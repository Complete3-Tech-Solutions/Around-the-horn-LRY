<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move the founder roster out of EventConfig into the DB so the moderator can
 * edit names / company / charity / headshot live from /admin. Headshots are
 * stored base64 in the row (served + cached by FounderController).
 */
final class Version20260619140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create founder table (editable roster + headshot)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE founder (
            id INTEGER NOT NULL,
            position INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            company VARCHAR(255) DEFAULT NULL,
            charity VARCHAR(255) DEFAULT NULL,
            headshot_data CLOB DEFAULT NULL,
            headshot_mime VARCHAR(100) DEFAULT NULL,
            version INTEGER NOT NULL DEFAULT 1,
            PRIMARY KEY(id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE founder');
    }
}

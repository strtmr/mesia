<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261122081753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Kaufzähler am Artikel (Sortierung „Erledigt“ nach Häufigkeit)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD purchase_count INT DEFAULT 0 NOT NULL');

        // Bisherige Käufe übernehmen (jeder Abhak-Vorgang steht in purchase_event)
        $this->addSql(<<<'SQL'
            UPDATE article a SET purchase_count = p.purchases
            FROM (SELECT article_id, COUNT(*) AS purchases FROM purchase_event WHERE article_id IS NOT NULL GROUP BY article_id) p
            WHERE a.id = p.article_id
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP purchase_count');
    }
}

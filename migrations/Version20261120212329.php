<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120212329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Herkunft der Artikel (Startkatalog oder Benutzer) für den Katalog-Abgleich';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD source VARCHAR(16) DEFAULT \'user\' NOT NULL');

        // Bestehende Artikel aus dem Startkatalog erkennen: Der Seeder legt Kategorien und Artikel eines Haushalts in
        // einem Durchgang mit demselben Zeitstempel an. Später angelegte Artikel stammen vom Benutzer.
        $this->addSql(<<<'SQL'
            UPDATE article a SET source = 'seed'
            FROM (SELECT household_id, MIN(created_at) AS seeded_at FROM category WHERE seed_key IS NOT NULL GROUP BY household_id) c
            WHERE a.household_id = c.household_id AND a.created_at = c.seeded_at
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP source');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261122091526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalform von Artikeln und Aliasen bis 200 Zeichen (ä → ae verlängert den Text)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ALTER name_normalized TYPE VARCHAR(200)');
        $this->addSql('ALTER TABLE article_alias ALTER alias_normalized TYPE VARCHAR(200)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ALTER name_normalized TYPE VARCHAR(100)');
        $this->addSql('ALTER TABLE article_alias ALTER alias_normalized TYPE VARCHAR(100)');
    }
}

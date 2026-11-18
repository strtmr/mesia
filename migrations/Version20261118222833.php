<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 0.5: Login und Haushalt.
 */
final class Version20261118222833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: Haushalte und Benutzer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id UUID NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, household_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX app_user_email_unique ON app_user (email)');
        $this->addSql('CREATE INDEX IDX_88BDF3E9E79FF843 ON app_user (household_id)');
        $this->addSql('CREATE TABLE household (id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E9E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP CONSTRAINT FK_88BDF3E9E79FF843');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE household');
    }
}

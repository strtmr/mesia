<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123073808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Push-Abos der Geräte (Mitteilungen)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_subscription (id UUID NOT NULL, user_id UUID NOT NULL, household_id UUID NOT NULL, endpoint VARCHAR(1000) NOT NULL, public_key VARCHAR(200) NOT NULL, auth_token VARCHAR(100) NOT NULL, frequency VARCHAR(20) NOT NULL, last_seq BIGINT NOT NULL, last_sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX push_subscription_household_idx ON push_subscription (household_id)');
        $this->addSql('CREATE UNIQUE INDEX push_subscription_endpoint_unique ON push_subscription (endpoint)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_subscription');
    }
}

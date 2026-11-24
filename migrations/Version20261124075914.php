<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124075914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Läden und „gültig bis“ an Listeneinträgen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE store (id UUID NOT NULL, name VARCHAR(40) NOT NULL, seed_key VARCHAR(40) DEFAULT NULL, household_id UUID NOT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX store_household_idx ON store (household_id)');
        $this->addSql('ALTER TABLE shopping_list_item ADD store_ids JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE shopping_list_item ADD valid_until DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopping_list_item DROP store_ids');
        $this->addSql('ALTER TABLE shopping_list_item DROP valid_until');
        $this->addSql('DROP TABLE store');
    }
}

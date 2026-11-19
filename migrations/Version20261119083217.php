<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1.1–1.3: Katalog, Einkaufsliste, Sync.
 */
final class Version20261119083217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1: Katalog, Einkaufsliste, Kaufhistorie, verarbeitete Sync-Mutationen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE article (id UUID NOT NULL, name VARCHAR(100) NOT NULL, name_normalized VARCHAR(100) NOT NULL, category_id UUID NOT NULL, default_unit VARCHAR(16) DEFAULT NULL, household_id UUID NOT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX article_household_idx ON article (household_id)');
        $this->addSql('CREATE UNIQUE INDEX article_name_unique ON article (household_id, name_normalized) WHERE deleted_at IS NULL');
        $this->addSql('CREATE TABLE article_alias (id UUID NOT NULL, article_id UUID NOT NULL, alias_normalized VARCHAR(100) NOT NULL, source VARCHAR(16) NOT NULL, household_id UUID NOT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX article_alias_household_idx ON article_alias (household_id)');
        $this->addSql('CREATE UNIQUE INDEX article_alias_unique ON article_alias (household_id, alias_normalized) WHERE deleted_at IS NULL');
        $this->addSql('CREATE TABLE category (id UUID NOT NULL, name VARCHAR(60) NOT NULL, icon VARCHAR(16) NOT NULL, sort_key VARCHAR(64) NOT NULL COLLATE "C", seed_key VARCHAR(40) DEFAULT NULL, is_fallback BOOLEAN NOT NULL, household_id UUID NOT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX category_household_idx ON category (household_id)');
        $this->addSql('CREATE TABLE item_contribution (active BOOLEAN NOT NULL, id UUID NOT NULL, source VARCHAR(20) NOT NULL, source_ref UUID DEFAULT NULL, quantity NUMERIC(10, 3) DEFAULT NULL, unit VARCHAR(16) DEFAULT NULL, created_by UUID DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, item_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_256FE3D4126F525E ON item_contribution (item_id)');
        $this->addSql('CREATE TABLE processed_mutation (mutation_id UUID NOT NULL, household_id UUID NOT NULL, device_id VARCHAR(64) NOT NULL, type VARCHAR(40) NOT NULL, status VARCHAR(16) NOT NULL, result JSONB NOT NULL, processed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (mutation_id))');
        $this->addSql('CREATE INDEX processed_mutation_household_idx ON processed_mutation (household_id, processed_at)');
        $this->addSql('CREATE TABLE purchase_event (id UUID NOT NULL, household_id UUID NOT NULL, item_id UUID NOT NULL, article_id UUID DEFAULT NULL, name VARCHAR(100) NOT NULL, quantity NUMERIC(10, 3) DEFAULT NULL, unit VARCHAR(16) DEFAULT NULL, user_id UUID DEFAULT NULL, purchased_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX purchase_event_household_idx ON purchase_event (household_id, purchased_at)');
        $this->addSql('CREATE INDEX purchase_event_item_idx ON purchase_event (item_id)');
        $this->addSql('CREATE TABLE shopping_list (id UUID NOT NULL, name VARCHAR(60) NOT NULL, household_id UUID NOT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX shopping_list_household_idx ON shopping_list (household_id)');
        $this->addSql('CREATE TABLE shopping_list_item (id UUID NOT NULL, list_id UUID NOT NULL, article_id UUID DEFAULT NULL, name VARCHAR(100) NOT NULL, category_id UUID NOT NULL, note VARCHAR(200) DEFAULT NULL, qty_override NUMERIC(10, 3) DEFAULT NULL, unit_override VARCHAR(16) DEFAULT NULL, sort_key VARCHAR(64) NOT NULL COLLATE "C", checked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, checked_by UUID DEFAULT NULL, activated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_by UUID DEFAULT NULL, updated_by UUID DEFAULT NULL, household_id UUID NOT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX shopping_list_item_household_idx ON shopping_list_item (household_id)');
        $this->addSql('CREATE UNIQUE INDEX shopping_list_item_article_unique ON shopping_list_item (list_id, article_id) WHERE deleted_at IS NULL AND article_id IS NOT NULL');
        $this->addSql('ALTER TABLE item_contribution ADD CONSTRAINT FK_256FE3D4126F525E FOREIGN KEY (item_id) REFERENCES shopping_list_item (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_contribution DROP CONSTRAINT FK_256FE3D4126F525E');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE article_alias');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE item_contribution');
        $this->addSql('DROP TABLE processed_mutation');
        $this->addSql('DROP TABLE purchase_event');
        $this->addSql('DROP TABLE shopping_list');
        $this->addSql('DROP TABLE shopping_list_item');
    }
}

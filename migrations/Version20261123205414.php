<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123205414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gerichte und Rezepte (Phase 2, Paket 2.1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dish (id UUID NOT NULL, household_id UUID NOT NULL, name VARCHAR(100) NOT NULL, effort VARCHAR(16) DEFAULT NULL, tags JSON DEFAULT \'[]\' NOT NULL, note TEXT DEFAULT NULL, source_url VARCHAR(1000) DEFAULT NULL, last_listed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_by UUID DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX dish_household_idx ON dish (household_id)');
        $this->addSql('CREATE TABLE ingredient_line (id UUID NOT NULL, position SMALLINT NOT NULL, raw_text VARCHAR(200) NOT NULL, quantity NUMERIC(10, 3) DEFAULT NULL, quantity_max NUMERIC(10, 3) DEFAULT NULL, unit VARCHAR(16) DEFAULT NULL, article_id UUID DEFAULT NULL, name VARCHAR(100) NOT NULL, note VARCHAR(200) DEFAULT NULL, optional BOOLEAN DEFAULT false NOT NULL, group_name VARCHAR(60) DEFAULT NULL, recipe_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8F45046059D8A214 ON ingredient_line (recipe_id)');
        $this->addSql('CREATE TABLE recipe (id UUID NOT NULL, servings SMALLINT NOT NULL, prep_minutes SMALLINT DEFAULT NULL, cook_minutes SMALLINT DEFAULT NULL, steps TEXT DEFAULT NULL, source_type VARCHAR(16) NOT NULL, is_default BOOLEAN NOT NULL, dish_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DA88B137148EB0CB ON recipe (dish_id)');
        $this->addSql('ALTER TABLE ingredient_line ADD CONSTRAINT FK_8F45046059D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B137148EB0CB FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingredient_line DROP CONSTRAINT FK_8F45046059D8A214');
        $this->addSql('ALTER TABLE recipe DROP CONSTRAINT FK_DA88B137148EB0CB');
        $this->addSql('DROP TABLE dish');
        $this->addSql('DROP TABLE ingredient_line');
        $this->addSql('DROP TABLE recipe');
    }
}

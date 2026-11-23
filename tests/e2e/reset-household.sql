-- Entfernt den Testhaushalt "E2E-Test" aus der Dev-Datenbank (nur Daten, kein Schema). Nie gegen Produktion ausführen!
BEGIN;
CREATE TEMP TABLE e2e_h ON COMMIT DROP AS SELECT id FROM household WHERE name = 'E2E-Test';
DELETE FROM item_contribution WHERE item_id IN (SELECT id FROM shopping_list_item WHERE household_id IN (SELECT id FROM e2e_h));
DELETE FROM shopping_list_item WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM shopping_list WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM article_alias WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM article WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM category WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM purchase_event WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM processed_mutation WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM change_log WHERE household_id IN (SELECT id FROM e2e_h);
-- Rezepte und Zutaten hängen per ON DELETE CASCADE am Gericht
DELETE FROM dish WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM push_subscription WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM app_user WHERE household_id IN (SELECT id FROM e2e_h);
DELETE FROM household WHERE id IN (SELECT id FROM e2e_h);
COMMIT;

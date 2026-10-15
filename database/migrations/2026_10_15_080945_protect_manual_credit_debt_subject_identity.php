<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $valid = 'NEW.reference_id IS NOT NULL AND NEW.counterparty_id IS NOT NULL AND NEW.reference_id = NEW.counterparty_id AND EXISTS (SELECT 1 FROM tracked_items p WHERE p.id = NEW.reference_id AND p.user_id = NEW.user_id AND p.account_id IS NULL)';
        $active = 'EXISTS (SELECT 1 FROM tracked_items p WHERE p.id = NEW.reference_id AND p.is_active = true)';
        $changed = 'NEW.reference_id IS DISTINCT FROM OLD.reference_id OR NEW.counterparty_id IS DISTINCT FROM OLD.counterparty_id OR NEW.user_id IS DISTINCT FROM OLD.user_id OR NEW.currency_code IS DISTINCT FROM OLD.currency_code';
        $history = "EXISTS (SELECT 1 FROM credit_debt_payments p WHERE p.credit_debt_item_id = OLD.id) OR EXISTS (SELECT 1 FROM counterparty_obligations o WHERE o.source_type = 'credit_debt_item' AND o.source_identity = CAST(OLD.uuid AS varchar)) OR EXISTS (SELECT 1 FROM counterparty_ledger_entries e WHERE e.source_type = 'credit_debt_item' AND e.source_identity = CAST(OLD.uuid AS varchar)) OR EXISTS (SELECT 1 FROM counterparty_source_links l WHERE l.source_type = 'credit_debt_item' AND CAST(l.source_identity AS varchar) = CAST(OLD.uuid AS varchar))";
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION protect_manual_subject_identity() RETURNS trigger AS \$\$ BEGIN
                IF TG_OP = 'INSERT' THEN
                    IF NOT ($valid) OR NOT ($active) THEN RAISE EXCEPTION 'Invalid manual economic subject' USING ERRCODE = '23514'; END IF;
                ELSE
                    IF NEW.reference_id IS DISTINCT FROM OLD.reference_id AND NOT ($active) THEN RAISE EXCEPTION 'Inactive manual economic subject' USING ERRCODE = '23514'; END IF;
                    IF NEW.uuid IS DISTINCT FROM OLD.uuid THEN RAISE EXCEPTION 'Manual source UUID is immutable' USING ERRCODE = '23514'; END IF;
                    IF (NEW.deleted_at IS NULL OR ($changed)) AND NOT ($valid) THEN RAISE EXCEPTION 'Invalid manual economic subject' USING ERRCODE = '23514'; END IF;
                    IF ($changed) AND ($history) THEN RAISE EXCEPTION 'Manual subject has payment or ledger history' USING ERRCODE = '23514'; END IF;
                END IF;
                RETURN NEW;
            END; \$\$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER credit_debt_subject_identity BEFORE INSERT OR UPDATE ON credit_debt_items FOR EACH ROW EXECUTE FUNCTION protect_manual_subject_identity()');

            return;
        }
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER credit_debt_subject_insert BEFORE INSERT ON credit_debt_items WHEN NOT ($valid) OR NOT ($active) BEGIN SELECT RAISE(ABORT, 'Invalid manual economic subject'); END");
            DB::unprepared("CREATE TRIGGER credit_debt_subject_update BEFORE UPDATE ON credit_debt_items BEGIN
                SELECT RAISE(ABORT, 'Invalid manual economic subject') WHERE (NEW.deleted_at IS NULL OR ($changed)) AND NOT ($valid);
                SELECT RAISE(ABORT, 'Inactive manual economic subject') WHERE NEW.reference_id IS DISTINCT FROM OLD.reference_id AND NOT ($active);
                SELECT RAISE(ABORT, 'Manual source UUID is immutable') WHERE NEW.uuid IS NOT OLD.uuid;
                SELECT RAISE(ABORT, 'Manual subject has payment or ledger history') WHERE ($changed) AND ($history);
            END");

            return;
        }
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $changed = 'NOT (NEW.reference_id <=> OLD.reference_id) OR NOT (NEW.counterparty_id <=> OLD.counterparty_id) OR NOT (NEW.user_id <=> OLD.user_id) OR NOT (NEW.currency_code <=> OLD.currency_code)';
            $history = str_replace('AS varchar', 'AS char', $history);
            DB::unprepared("CREATE TRIGGER credit_debt_subject_insert BEFORE INSERT ON credit_debt_items FOR EACH ROW BEGIN
                IF NOT ($valid) OR NOT ($active) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid manual economic subject'; END IF;
            END");
            DB::unprepared("CREATE TRIGGER credit_debt_subject_update BEFORE UPDATE ON credit_debt_items FOR EACH ROW BEGIN
                IF (NEW.deleted_at IS NULL OR ($changed)) AND NOT ($valid) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid manual economic subject'; END IF;
                IF NOT (NEW.reference_id <=> OLD.reference_id) AND NOT ($active) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inactive manual economic subject'; END IF;
                IF NOT (NEW.uuid <=> OLD.uuid) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Manual source UUID is immutable'; END IF;
                IF ($changed) AND ($history) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Manual subject has payment or ledger history'; END IF;
            END");

            return;
        }
        throw new RuntimeException('Unsupported database for manual economic subject protection.');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS credit_debt_subject_identity ON credit_debt_items');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_manual_subject_identity()');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS credit_debt_subject_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS credit_debt_subject_update');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Edges can only target active roots. Immutable edges and permanently inactive
     * sources prevent cycles while allowing roots to become secondary references.
     */
    public function up(): void
    {
        Schema::create('tracked_item_aliases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_tracked_item_id')->unique()->constrained('tracked_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('canonical_tracked_item_id')->index()->constrained('tracked_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('created_at')->useCurrent();
        });

        $validAlias = 'NEW.source_tracked_item_id <> NEW.canonical_tracked_item_id AND EXISTS (
            SELECT 1 FROM tracked_items s JOIN tracked_items d ON d.id = NEW.canonical_tracked_item_id
            WHERE s.id = NEW.source_tracked_item_id AND s.is_active = false AND d.is_active = true
            AND ((s.account_id IS NULL AND d.account_id IS NULL AND s.user_id = d.user_id)
                OR (s.account_id IS NOT NULL AND s.account_id = d.account_id))
        ) AND NOT EXISTS (SELECT 1 FROM tracked_item_aliases a WHERE a.source_tracked_item_id = NEW.canonical_tracked_item_id)';
        $isSecondary = 'EXISTS (SELECT 1 FROM tracked_item_aliases a WHERE a.source_tracked_item_id = OLD.id)';
        $isReferenced = 'EXISTS (SELECT 1 FROM tracked_item_aliases a WHERE a.source_tracked_item_id = OLD.id OR a.canonical_tracked_item_id = OLD.id)';

        match (DB::getDriverName()) {
            'sqlite' => $this->protectSqlite($validAlias, $isSecondary, $isReferenced),
            'pgsql' => $this->protectPostgres($validAlias, $isSecondary, $isReferenced),
            'mysql', 'mariadb' => $this->protectMysql(),
            default => throw new RuntimeException('Unsupported database for tracked item identity protection.'),
        };
    }

    private function protectSqlite(string $validAlias, string $isSecondary, string $isReferenced): void
    {
        DB::unprepared("CREATE TRIGGER tracked_item_alias_insert BEFORE INSERT ON tracked_item_aliases BEGIN
            SELECT RAISE(ABORT, 'Invalid tracked item alias: source must be inactive, target must be an active root in the same catalog') WHERE NOT ($validAlias);
        END");
        foreach (['update', 'delete'] as $operation) {
            DB::unprepared("CREATE TRIGGER tracked_item_alias_no_{$operation} BEFORE {$operation} ON tracked_item_aliases BEGIN
                SELECT RAISE(ABORT, 'Tracked item aliases are immutable');
            END");
        }
        DB::unprepared("CREATE TRIGGER tracked_item_identity_update BEFORE UPDATE ON tracked_items BEGIN
            SELECT RAISE(ABORT, 'A secondary reference cannot be reactivated') WHERE NEW.is_active = true AND ($isSecondary);
            SELECT RAISE(ABORT, 'An aliased catalog identity cannot change')
                WHERE (NEW.user_id IS NOT OLD.user_id OR NEW.account_id IS NOT OLD.account_id OR NEW.uuid IS NOT OLD.uuid) AND ($isReferenced);
        END");
    }

    private function protectPostgres(string $validAlias, string $isSecondary, string $isReferenced): void
    {
        DB::unprepared("CREATE OR REPLACE FUNCTION protect_tracked_item_alias_insert() RETURNS trigger AS \$\$ BEGIN
            PERFORM id FROM tracked_items WHERE id IN (NEW.source_tracked_item_id, NEW.canonical_tracked_item_id) ORDER BY id FOR UPDATE;
            IF NOT ($validAlias) THEN
                RAISE EXCEPTION 'Invalid tracked item alias: source must be inactive, target must be an active root in the same catalog' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END; \$\$ LANGUAGE plpgsql");
        DB::unprepared('CREATE TRIGGER tracked_item_alias_insert BEFORE INSERT ON tracked_item_aliases FOR EACH ROW EXECUTE FUNCTION protect_tracked_item_alias_insert()');
        DB::unprepared("CREATE OR REPLACE FUNCTION reject_tracked_item_alias_mutation() RETURNS trigger AS \$\$ BEGIN
            RAISE EXCEPTION 'Tracked item aliases are immutable' USING ERRCODE = '23514';
        END; \$\$ LANGUAGE plpgsql");
        foreach (['update', 'delete'] as $operation) {
            DB::unprepared("CREATE TRIGGER tracked_item_alias_no_{$operation} BEFORE {$operation} ON tracked_item_aliases FOR EACH ROW EXECUTE FUNCTION reject_tracked_item_alias_mutation()");
        }
        DB::unprepared("CREATE OR REPLACE FUNCTION protect_tracked_item_identity_update() RETURNS trigger AS \$\$ BEGIN
            IF NEW.is_active = true AND ($isSecondary) THEN
                RAISE EXCEPTION 'A secondary reference cannot be reactivated' USING ERRCODE = '23514';
            END IF;
            IF (NEW.user_id IS DISTINCT FROM OLD.user_id OR NEW.account_id IS DISTINCT FROM OLD.account_id OR NEW.uuid IS DISTINCT FROM OLD.uuid) AND ($isReferenced) THEN
                RAISE EXCEPTION 'An aliased catalog identity cannot change' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END; \$\$ LANGUAGE plpgsql");
        DB::unprepared('CREATE TRIGGER tracked_item_identity_update BEFORE UPDATE ON tracked_items FOR EACH ROW EXECUTE FUNCTION protect_tracked_item_identity_update()');
    }

    private function protectMysql(): void
    {
        DB::unprepared("CREATE TRIGGER tracked_item_alias_insert BEFORE INSERT ON tracked_item_aliases FOR EACH ROW BEGIN
            DECLARE locked_id BIGINT;
            DECLARE source_owner BIGINT;
            DECLARE target_owner BIGINT;
            DECLARE source_account BIGINT;
            DECLARE target_account BIGINT;
            DECLARE source_active BOOLEAN;
            DECLARE target_active BOOLEAN;
            DECLARE target_alias BIGINT;
            SELECT id INTO locked_id FROM tracked_items WHERE id = LEAST(NEW.source_tracked_item_id, NEW.canonical_tracked_item_id) FOR UPDATE;
            SELECT id INTO locked_id FROM tracked_items WHERE id = GREATEST(NEW.source_tracked_item_id, NEW.canonical_tracked_item_id) FOR UPDATE;
            SELECT user_id, account_id, is_active INTO source_owner, source_account, source_active FROM tracked_items WHERE id = NEW.source_tracked_item_id FOR UPDATE;
            SELECT user_id, account_id, is_active INTO target_owner, target_account, target_active FROM tracked_items WHERE id = NEW.canonical_tracked_item_id FOR UPDATE;
            SET target_alias = (SELECT id FROM tracked_item_aliases WHERE source_tracked_item_id = NEW.canonical_tracked_item_id LIMIT 1 FOR UPDATE);
            IF NEW.source_tracked_item_id = NEW.canonical_tracked_item_id OR source_owner IS NULL OR target_owner IS NULL
                OR source_active <> false OR target_active <> true OR target_alias IS NOT NULL
                OR NOT ((source_account IS NULL AND target_account IS NULL AND source_owner = target_owner)
                    OR (source_account IS NOT NULL AND source_account <=> target_account)) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid tracked item alias';
            END IF;
        END");
        foreach (['update', 'delete'] as $operation) {
            DB::unprepared("CREATE TRIGGER tracked_item_alias_no_{$operation} BEFORE {$operation} ON tracked_item_aliases FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tracked item aliases are immutable'");
        }
        DB::unprepared("CREATE TRIGGER tracked_item_identity_update BEFORE UPDATE ON tracked_items FOR EACH ROW BEGIN
            DECLARE source_alias BIGINT;
            DECLARE incoming_alias BIGINT;
            SET source_alias = (SELECT id FROM tracked_item_aliases WHERE source_tracked_item_id = OLD.id LIMIT 1 FOR UPDATE);
            SET incoming_alias = (SELECT id FROM tracked_item_aliases WHERE canonical_tracked_item_id = OLD.id ORDER BY id LIMIT 1 FOR UPDATE);
            IF NEW.is_active = true AND source_alias IS NOT NULL THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A secondary reference cannot be reactivated';
            END IF;
            IF (NOT (NEW.user_id <=> OLD.user_id) OR NOT (NEW.account_id <=> OLD.account_id) OR NOT (NEW.uuid <=> OLD.uuid))
                AND (source_alias IS NOT NULL OR incoming_alias IS NOT NULL) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An aliased catalog identity cannot change';
            END IF;
        END");
    }

    public function down(): void
    {
        if (DB::table('tracked_item_aliases')->exists()) {
            throw new RuntimeException('Cannot remove persisted tracked item aliases. A forward migration is required.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS tracked_item_identity_update ON tracked_items');
            Schema::dropIfExists('tracked_item_aliases');
            foreach (['protect_tracked_item_alias_insert', 'reject_tracked_item_alias_mutation', 'protect_tracked_item_identity_update'] as $function) {
                DB::unprepared("DROP FUNCTION IF EXISTS {$function}()");
            }

            return;
        }
        foreach (['tracked_item_alias_insert', 'tracked_item_alias_no_update', 'tracked_item_alias_no_delete', 'tracked_item_identity_update'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
        Schema::dropIfExists('tracked_item_aliases');
    }
};

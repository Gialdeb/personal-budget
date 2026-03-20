<?php

use App\Enums\ImportRowStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table): void {
            $table->foreignId('import_format_id')
                ->nullable()
                ->after('account_id')
                ->constrained('import_formats')
                ->nullOnDelete();
            $table->unsignedInteger('rows_count')->default(0)->after('status');
            $table->unsignedInteger('ready_rows_count')->default(0)->after('rows_count');
            $table->unsignedInteger('review_rows_count')->default(0)->after('ready_rows_count');
            $table->unsignedInteger('invalid_rows_count')->default(0)->after('review_rows_count');
            $table->unsignedInteger('duplicate_rows_count')->default(0)->after('invalid_rows_count');
            $table->unsignedInteger('imported_rows_count')->default(0)->after('duplicate_rows_count');
            $table->timestamp('rolled_back_at')->nullable()->after('imported_at');
            $table->timestamp('completed_at')->nullable()->after('rolled_back_at');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->jsonb('meta')->nullable()->after('error_message');
        });

        Schema::table('import_rows', function (Blueprint $table): void {
            $table->string('status', 30)->default(ImportRowStatusEnum::PARSED->value)->after('parse_status');
            $table->foreignId('transaction_id')
                ->nullable()
                ->after('status')
                ->constrained('transactions')
                ->nullOnDelete();
            $table->string('fingerprint', 255)->nullable()->after('transaction_id');
            $table->jsonb('normalized_payload')->nullable()->after('raw_payload');
            $table->jsonb('errors')->nullable()->after('parse_error');
            $table->jsonb('warnings')->nullable()->after('errors');
            $table->timestamp('rolled_back_at')->nullable()->after('warnings');
            $table->timestamp('imported_at')->nullable()->after('rolled_back_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropColumn([
                'status',
                'fingerprint',
                'normalized_payload',
                'errors',
                'warnings',
                'rolled_back_at',
                'imported_at',
            ]);
        });

        Schema::table('imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('import_format_id');
            $table->dropColumn([
                'rows_count',
                'ready_rows_count',
                'review_rows_count',
                'invalid_rows_count',
                'duplicate_rows_count',
                'imported_rows_count',
                'rolled_back_at',
                'completed_at',
                'failed_at',
                'meta',
            ]);
        });
    }
};

<?php

use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
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
        Schema::create('import_formats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();

            $table->string('code')->unique();
            $table->string('name');
            $table->string('version', 50)->default('v1');
            $table->string('type', 50)->default(ImportFormatTypeEnum::GENERIC_CSV->value);
            $table->string('status', 50)->default(ImportFormatStatusEnum::ACTIVE->value);

            $table->boolean('is_generic')->default(false);
            $table->text('notes')->nullable();
            $table->jsonb('settings')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_formats');
    }
};

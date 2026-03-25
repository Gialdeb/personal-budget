<?php

use App\Enums\CommunicationTemplateOverrideScopeEnum;
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
        Schema::create('communication_template_overrides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('communication_template_id')
                ->constrained('communication_templates')
                ->cascadeOnDelete();

            $table->string('scope')
                ->default(CommunicationTemplateOverrideScopeEnum::GLOBAL->value);

            $table->string('scope_key')->nullable();

            $table->string('subject_template')->nullable();
            $table->string('title_template')->nullable();
            $table->longText('body_template')->nullable();
            $table->string('cta_label_template')->nullable();
            $table->text('cta_url_template')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['communication_template_id', 'scope', 'is_active'], 'comm_template_override_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_template_overrides');
    }
};

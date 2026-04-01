<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('riad_code', 32)->nullable()->after('country_code');
            $table->string('lei', 32)->nullable()->after('riad_code');
            $table->string('address')->nullable()->after('lei');
            $table->string('postal_code', 32)->nullable()->after('address');
            $table->string('city', 150)->nullable()->after('postal_code');
            $table->string('category', 120)->nullable()->after('city');
            $table->string('head_country_code', 2)->nullable()->after('category');
            $table->string('head_name', 150)->nullable()->after('head_country_code');
            $table->string('head_riad_code', 32)->nullable()->after('head_name');
            $table->string('head_lei', 32)->nullable()->after('head_riad_code');
            $table->string('report_label')->nullable()->after('head_lei');
            $table->string('logo_path')->nullable()->after('report_label');
            $table->string('logo_url')->nullable()->after('logo_path');
            $table->unsignedInteger('sort_order')->nullable()->after('logo_url');

            $table->dropUnique('banks_slug_unique');
            $table->unique('riad_code', 'banks_riad_code_unique');
            $table->unique(['country_code', 'slug'], 'banks_country_code_slug_unique');
            $table->index('country_code', 'banks_country_code_index');
            $table->index(['country_code', 'name'], 'banks_country_code_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropIndex('banks_country_code_name_index');
            $table->dropIndex('banks_country_code_index');
            $table->dropUnique('banks_country_code_slug_unique');
            $table->dropUnique('banks_riad_code_unique');

            $table->dropColumn([
                'riad_code',
                'lei',
                'address',
                'postal_code',
                'city',
                'category',
                'head_country_code',
                'head_name',
                'head_riad_code',
                'head_lei',
                'report_label',
                'logo_path',
                'logo_url',
                'sort_order',
            ]);

            $table->unique('slug');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('number_thousands_separator', 8)->default('.')->after('format_locale');
            $table->string('number_decimal_separator', 8)->default(',')->after('number_thousands_separator');
            $table->string('date_format', 24)->default('D MMM YYYY')->after('number_decimal_separator');
        });

        DB::table('users')
            ->where('format_locale', 'en-GB')
            ->update([
                'number_thousands_separator' => ',',
                'number_decimal_separator' => '.',
                'date_format' => 'DD/MM/YYYY',
            ]);

        DB::table('users')
            ->where('format_locale', 'en-US')
            ->update([
                'number_thousands_separator' => ',',
                'number_decimal_separator' => '.',
                'date_format' => 'MMM D, YYYY',
            ]);

        DB::table('users')
            ->where(function ($query): void {
                $query->whereNull('format_locale')
                    ->orWhereNotIn('format_locale', ['en-GB', 'en-US']);
            })
            ->update([
                'number_thousands_separator' => '.',
                'number_decimal_separator' => ',',
                'date_format' => 'D MMM YYYY',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'number_thousands_separator',
                'number_decimal_separator',
                'date_format',
            ]);
        });
    }
};

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
        Schema::table('accounts', function (Blueprint $table): void {
            $table->string('currency_code', 3)
                ->nullable()
                ->default('EUR');
        });

        DB::table('accounts')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                $currencyCodesByUser = DB::table('users')
                    ->whereIn('id', $accounts->pluck('user_id')->unique())
                    ->pluck('base_currency_code', 'id');

                foreach ($accounts as $account) {
                    DB::table('accounts')
                        ->where('id', $account->id)
                        ->update([
                            'currency_code' => $currencyCodesByUser[$account->user_id] ?? 'EUR',
                        ]);
                }
            });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->string('currency_code', 3)
                ->nullable(false)
                ->default('EUR')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('currency_code');
        });
    }
};

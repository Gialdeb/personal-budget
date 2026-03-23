<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->date('opening_balance_date')->nullable()->after('opening_balance');
        });

        DB::table('accounts')
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $openingDate = DB::table('transactions')
                        ->where('account_id', $account->id)
                        ->where('kind', 'opening_balance')
                        ->orderBy('transaction_date')
                        ->orderBy('id')
                        ->value('transaction_date');

                    if ($openingDate === null) {
                        continue;
                    }

                    DB::table('accounts')
                        ->where('id', $account->id)
                        ->update([
                            'opening_balance_date' => $openingDate,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('opening_balance_date');
        });
    }
};

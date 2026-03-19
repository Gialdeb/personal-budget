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
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('user_bank_id')
                ->nullable()
                ->after('bank_id')
                ->constrained('user_banks')
                ->nullOnDelete();

            $table->index(['user_id', 'user_bank_id']);
        });

        $bankCatalog = DB::table('banks')
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $accountBanks = DB::table('accounts')
            ->select('user_id', 'bank_id')
            ->whereNotNull('bank_id')
            ->distinct()
            ->get();

        foreach ($accountBanks as $accountBank) {
            $bank = $bankCatalog->get($accountBank->bank_id);

            if ($bank === null) {
                continue;
            }

            $existingUserBankId = DB::table('user_banks')
                ->where('user_id', $accountBank->user_id)
                ->where('bank_id', $accountBank->bank_id)
                ->value('id');

            $userBankId = $existingUserBankId;

            if ($userBankId === null) {
                $userBankId = DB::table('user_banks')->insertGetId([
                    'user_id' => $accountBank->user_id,
                    'bank_id' => $accountBank->bank_id,
                    'name' => $bank->name,
                    'slug' => $bank->slug,
                    'is_custom' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('accounts')
                ->where('user_id', $accountBank->user_id)
                ->where('bank_id', $accountBank->bank_id)
                ->update([
                    'user_bank_id' => $userBankId,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_bank_id');
        });
    }
};

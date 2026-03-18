<?php

namespace Database\Seeders;

use App\Enums\TransactionReviewActionEnum;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class FakeTransactionReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $transaction = Transaction::where('user_id', $user->id)
            ->whereDate('transaction_date', '2025-08-18')
            ->where('description', 'Prenotazione weekend agosto 2025')
            ->first();

        if (! $transaction) {
            return;
        }

        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');

        TransactionReview::updateOrCreate(
            [
                'transaction_id' => $transaction->id,
                'reviewed_by' => $user->id,
            ],
            [
                'old_category_id' => $categories['Extra']->id ?? null,
                'new_category_id' => $categories['Tempo libero']->id ?? null,
                'old_scope_id' => $transaction->scope_id,
                'new_scope_id' => $transaction->scope_id,
                'old_merchant_id' => $transaction->merchant_id,
                'new_merchant_id' => $transaction->merchant_id,
                'review_action' => TransactionReviewActionEnum::CORRECTED,
                'notes' => 'Review seed demo',
            ]
        );
    }
}

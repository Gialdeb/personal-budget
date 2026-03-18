<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Scope;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FakeTransactionSplitSeeder extends Seeder
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
            ->where('description', 'Spesa supermercato')
            ->first();

        if (! $transaction) {
            return;
        }

        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');
        $scope = Scope::where('user_id', $user->id)->where('name', 'Personale')->first();

        $rows = [
            ['category' => 'Alimentari', 'amount' => 70.00],
            ['category' => 'Extra', 'amount' => 16.00],
        ];

        foreach ($rows as $row) {
            TransactionSplit::updateOrCreate(
                [
                    'transaction_id' => $transaction->id,
                    'category_id' => $categories[$row['category']]->id ?? null,
                ],
                [
                    'scope_id' => $scope?->id,
                    'merchant_id' => $transaction->merchant_id,
                    'amount' => $row['amount'],
                    'notes' => 'Split seed demo',
                ]
            );
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\HouseholdMembershipStatusEnum;
use App\Enums\HouseholdRoleEnum;
use App\Enums\HouseholdStatusEnum;
use App\Enums\MembershipSourceEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\Household;
use App\Models\HouseholdMembership;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillHouseholdsAndAccountMemberships extends Command
{
    protected $signature = 'app:backfill-households-accounts {--dry-run}';

    protected $description = 'Create personal households and owner account memberships for existing data';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Running in dry-run mode.' : 'Running backfill.');

        User::query()->chunkById(100, function ($users) use ($dryRun) {
            foreach ($users as $user) {
                $household = Household::query()->firstOrCreate(
                    ['owner_user_id' => $user->id, 'slug' => 'user-'.$user->id.'-personal'],
                    [
                        'uuid' => (string) Str::uuid(),
                        'name' => trim(($user->name ?? 'User').' personal'),
                        'status' => HouseholdStatusEnum::ACTIVE,
                        'settings' => null,
                    ]
                );

                HouseholdMembership::query()->firstOrCreate(
                    [
                        'household_id' => $household->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'role' => HouseholdRoleEnum::OWNER,
                        'status' => HouseholdMembershipStatusEnum::ACTIVE,
                        'permissions' => null,
                        'invited_by_user_id' => null,
                        'joined_at' => now(),
                    ]
                );

                if ($dryRun) {
                    $this->line("Prepared household for user #{$user->id}");
                }
            }
        });

        Account::query()->chunkById(100, function ($accounts) use ($dryRun) {
            foreach ($accounts as $account) {
                if (! $account->user_id) {
                    $this->warn("Account #{$account->id} has no user_id, skipped.");

                    continue;
                }

                $household = Household::query()
                    ->where('owner_user_id', $account->user_id)
                    ->where('slug', 'user-'.$account->user_id.'-personal')
                    ->first();

                if (! $household) {
                    $this->warn("No personal household found for account #{$account->id}, skipped.");

                    continue;
                }

                if (! $dryRun && $account->household_id !== $household->id) {
                    $account->household_id = $household->id;
                    $account->save();
                }

                AccountMembership::query()->firstOrCreate(
                    [
                        'account_id' => $account->id,
                        'user_id' => $account->user_id,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'household_id' => $household->id,
                        'role' => AccountMembershipRoleEnum::OWNER,
                        'status' => AccountMembershipStatusEnum::ACTIVE,
                        'permissions' => null,
                        'granted_by_user_id' => $account->user_id,
                        'source' => MembershipSourceEnum::MIGRATION,
                        'joined_at' => $account->created_at ?? now(),
                    ]
                );

                if ($dryRun) {
                    $this->line("Prepared account membership for account #{$account->id}");
                }
            }
        });

        $this->info('Backfill completed.');

        return self::SUCCESS;
    }
}

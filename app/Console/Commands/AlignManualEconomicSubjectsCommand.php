<?php

namespace App\Console\Commands;

use App\Models\CreditDebtItem;
use App\Models\User;
use App\Services\CreditDebts\AlignManualEconomicSubjects;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AlignManualEconomicSubjectsCommand extends Command
{
    protected $signature = 'credits-debts:align-subjects {--user= : User ID or UUID} {--item= : Item UUID} {--apply} {--dry-run}';

    protected $description = 'Audit and align only unambiguous manual economic subjects; defaults to dry-run';

    public function handle(AlignManualEconomicSubjects $alignment): int
    {
        $selection = (string) $this->option('user');
        if ((! ctype_digit($selection) && ! Str::isUuid($selection)) || ($this->option('apply') && $this->option('dry-run'))) {
            $this->error('Select --user by ID or UUID and either --apply or --dry-run.');

            return self::FAILURE;
        }
        $user = User::query()->where(ctype_digit($selection) ? 'id' : 'uuid', $selection)->first();
        if ($user === null || ($this->option('item') && ! Str::isUuid($this->option('item')))) {
            $this->error('Invalid user or item.');

            return self::FAILURE;
        }
        if ($this->option('item') && ! CreditDebtItem::withTrashed()->forUser($user)->where('uuid', $this->option('item'))->exists()) {
            $this->error('Item not found in the selected user scope.');

            return self::FAILURE;
        }
        CreditDebtItem::withTrashed()->forUser($user)->when($this->option('item'), fn ($query) => $query->where('uuid', $this->option('item')))->orderBy('id')->chunkById(100, function ($items) use ($alignment, $user): void {
            foreach ($items as $item) {
                $this->line(json_encode($alignment->item($user, $item->uuid, (bool) $this->option('apply')), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            }
        });

        return self::SUCCESS;
    }
}

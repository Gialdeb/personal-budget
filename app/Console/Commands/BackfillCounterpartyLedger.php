<?php

namespace App\Console\Commands;

use App\Models\CounterpartyRelation;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Counterparties\CounterpartyBackfillService;
use Illuminate\Console\Command;
use Throwable;

class BackfillCounterpartyLedger extends Command
{
    protected $signature = 'counterparties:backfill {--relation= : Relation UUID} {--counterparty= : Personal counterparty UUID} {--user= : User UUID or ID} {--currency= : Currency code} {--apply : Apply instead of the default dry-run} {--dry-run : Explicit dry-run}';

    protected $description = 'Plan or explicitly import legacy counterparty facts; no bank movements are created';

    public function handle(CounterpartyBackfillService $backfill): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Use either --apply or --dry-run.');

            return self::FAILURE;
        }
        $relation = $this->option('relation') ? CounterpartyRelation::query()->where('uuid', $this->option('relation'))->first() : null;
        if ($this->option('relation') && $relation === null) {
            $this->error('Relation not found.');

            return self::FAILURE;
        }
        $party = $this->option('counterparty') ? TrackedItem::query()->whereNull('account_id')->where('uuid', $this->option('counterparty'))->first() : $relation?->counterparty;
        if ($this->option('counterparty') && $party === null) {
            $this->error('Personal counterparty not found.');

            return self::FAILURE;
        }
        $selection = $this->option('user');
        $user = $selection ? User::query()->where('uuid', $selection)->when(ctype_digit((string) $selection), fn ($query) => $query->orWhere('id', (int) $selection))->first() : ($relation?->user ?? $party?->user);
        if ($user === null || ($party !== null && $party->user_id !== $user->id) || ($relation !== null && ($relation->user_id !== $user->id || $party?->id !== $relation->counterparty_id || ($this->option('currency') && $this->option('currency') !== $relation->currency_code)))) {
            $this->error('Select a consistent --relation, --counterparty or --user scope.');

            return self::FAILURE;
        }
        $parties = $party === null ? TrackedItem::query()->sharedExpenseHistory($user->id)->get()->concat(CounterpartyRelation::query()->where('user_id', $user->id)->with('counterparty')->get()->pluck('counterparty'))->unique('id') : collect([$party]);
        $failed = false;
        foreach ($parties as $selectedParty) {
            $currencies = $this->option('currency') ? [$this->option('currency')] : ($relation !== null ? [$relation->currency_code] : $backfill->currencies($user, $selectedParty));
            foreach ($currencies as $currency) {
                try {
                    $this->line(json_encode($backfill->run($user, $selectedParty, $currency, (bool) $this->option('apply')), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                } catch (Throwable $exception) {
                    $failed = true;
                    $this->error($selectedParty->uuid.' '.$currency.': '.$exception->getMessage());
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

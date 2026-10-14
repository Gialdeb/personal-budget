<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\DeviceToken;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\CounterpartySharedSourceObserver;
use App\Policies\AccountInvitationPolicy;
use App\Policies\AccountMembershipPolicy;
use App\Policies\AccountPolicy;
use App\Services\Counterparties\CounterpartyBankMutation;
use App\Services\Counterparties\SharedCounterpartyShadowService;
use App\Support\AuthenticatedSsrGateway;
use App\Support\Sentinel\HorizonDriver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Ssr\Gateway;
use Laravel\Sentinel\Sentinel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SharedCounterpartyShadowService::class);
        $this->app->scoped(CounterpartyBankMutation::class);
        $this->app->bind(Gateway::class, AuthenticatedSsrGateway::class);
        Sentinel::extend('horizon', fn ($app) => new HorizonDriver(fn () => $app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        foreach ([Transaction::class, SharedExpenseLedgerEntry::class, SharedExpenseSettlement::class] as $source) {
            $source::observe(CounterpartySharedSourceObserver::class);
        }

        Event::listen(Logout::class, function (Logout $event): void {
            if (! request()->hasSession() || ! $event->user instanceof User) {
                return;
            }

            $deviceUuid = request()->session()->pull('push_device_token_uuid');

            if (is_string($deviceUuid)) {
                DeviceToken::query()->where('user_id', $event->user->id)->where('uuid', $deviceUuid)->update([
                    'is_active' => false,
                    'invalidated_at' => now(),
                    'invalidation_reason' => 'session_logout',
                ]);
            }
        });

        Gate::define('viewLogViewer', fn (?User $user): bool => $user?->isAdmin() === true);
        Gate::define('deleteLogFile', fn (): bool => false);
        Gate::define('deleteLogFolder', fn (): bool => false);
        Gate::define('downloadLogFile', fn (): bool => false);
        Gate::define('downloadLogFolder', fn (): bool => false);

        Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            Log::withContext([
                'job_class' => $event->job->resolveName(),
                'job_id' => $event->job->getJobId(),
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'attempt' => $event->job->attempts(),
            ]);
            try {
                report($event->exception);
            } finally {
                Log::withoutContext(['job_class', 'job_id', 'queue', 'connection', 'attempt']);
            }
        });
        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event): void {
            Log::withContext(['scheduled_task' => $event->task->description ?? $event->task::class]);
            try {
                report($event->exception);
            } finally {
                Log::withoutContext(['scheduled_task']);
            }
        });

        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(AccountInvitation::class, AccountInvitationPolicy::class);
        Gate::policy(AccountMembership::class, AccountMembershipPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

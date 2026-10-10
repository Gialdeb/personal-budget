<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use App\Policies\AccountInvitationPolicy;
use App\Policies\AccountMembershipPolicy;
use App\Policies\AccountPolicy;
use App\Support\Sentinel\HorizonDriver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sentinel\Sentinel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Sentinel::extend('horizon', fn ($app) => new HorizonDriver(fn () => $app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

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

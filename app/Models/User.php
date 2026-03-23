<?php

namespace App\Models;

use App\Enums\UserStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use App\Notifications\Auth\LocalizedResetPassword;
use App\Notifications\Auth\LocalizedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'surname', 'email', 'password', 'locale', 'base_currency_code', 'format_locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, HasRoles, Impersonate, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'locale' => 'string',
            'status_changed_at' => 'datetime',
            'subscription_started_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'is_impersonable' => 'boolean',
            'base_currency_code' => 'string',
            'format_locale' => 'string',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatusEnum::ACTIVE->value;
    }

    public function isBanned(): bool
    {
        return $this->status === UserStatusEnum::BANNED->value;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatusEnum::SUSPENDED->value;
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(Scope::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function userBanks(): HasMany
    {
        return $this->hasMany(UserBank::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function transactionMatchers(): HasMany
    {
        return $this->hasMany(TransactionMatcher::class);
    }

    public function transactionTrainingSamples(): HasMany
    {
        return $this->hasMany(TransactionTrainingSample::class);
    }

    public function recurringEntries(): HasMany
    {
        return $this->hasMany(RecurringEntry::class);
    }

    public function scheduledEntries(): HasMany
    {
        return $this->hasMany(ScheduledEntry::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function years(): User|HasMany
    {
        return $this->hasMany(UserYear::class);
    }

    public function trackedItems(): HasMany
    {
        return $this->hasMany(TrackedItem::class);
    }

    public function preferredLocale(): string
    {
        if (is_string($this->locale) && array_key_exists($this->locale, config('locales.supported', []))) {
            return $this->locale;
        }

        return App::currentLocale();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new LocalizedResetPassword($token)->locale($this->preferredLocale()));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new LocalizedVerifyEmail)->locale($this->preferredLocale()));
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->hasRole('admin') && (bool) $this->is_impersonable;
    }

    public function cannotBeImpersonated(): bool
    {
        return ! $this->canBeImpersonated();
    }

    public function canImpersonate(): bool
    {
        return $this->hasRole('admin');
    }

    public function baseCurrencyCode(): string
    {
        return $this->base_currency_code;
    }

    public function canChangeBaseCurrency(): bool
    {
        return ! $this->accounts()->exists() && ! $this->transactions()->exists();
    }

    public function formatLocale(): string
    {
        return $this->format_locale;
    }
}

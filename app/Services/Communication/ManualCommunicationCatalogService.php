<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannelEnum;
use App\Enums\NotificationAudienceEnum;
use App\Models\CommunicationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ManualCommunicationCatalogService
{
    public function __construct(
        protected CommunicationCategoryChannelService $categoryChannelService,
    ) {}

    /**
     * @return Builder<CommunicationCategory>
     */
    public function manualCategoriesQuery(): Builder
    {
        return $this->categoryChannelService
            ->categoriesQuery()
            ->where('is_active', true)
            ->whereIn('audience', [
                NotificationAudienceEnum::USER->value,
                NotificationAudienceEnum::BOTH->value,
            ])
            ->where('context_type', 'user')
            ->orderBy('name')
            ->orderBy('key');
    }

    public function availableForManualSend(CommunicationCategory $category): bool
    {
        return $this->categoryChannelService->availableForManualSend($category);
    }

    /**
     * @return array<int, string>
     */
    public function displayChannelValues(): array
    {
        return $this->categoryChannelService->displayChannelValues();
    }

    public function findManualCategoryByUuid(string $uuid): ?CommunicationCategory
    {
        return $this->manualCategoriesQuery()
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * @return array<int, CommunicationChannelEnum>
     */
    public function availableChannels(CommunicationCategory $category): array
    {
        return $this->categoryChannelService->activeDefaultChannels($category);
    }

    /**
     * @return array<int, string>
     */
    public function selectableChannelValues(CommunicationCategory $category): array
    {
        return $this->categoryChannelService->selectableChannelValues($category);
    }

    public function fixedChannelValue(CommunicationCategory $category): ?string
    {
        return $this->categoryChannelService->fixedChannelValue($category);
    }

    /**
     * @return Builder<User>
     */
    public function recipientQuery(?string $search = null): Builder
    {
        $normalizedSearch = trim((string) $search);
        $searchTerms = collect(preg_split('/\s+/', $normalizedSearch) ?: [])
            ->filter(fn ($term) => is_string($term) && $term !== '')
            ->values()
            ->all();

        return User::query()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'admin'))
            ->when($searchTerms !== [], function (Builder $query) use ($searchTerms): void {
                foreach ($searchTerms as $term) {
                    $query->where(function (Builder $searchQuery) use ($term): void {
                        $searchQuery
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('surname', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
                }
            })
            ->orderBy('name')
            ->orderBy('surname')
            ->orderBy('email');
    }

    public function findRecipientByUuid(string $uuid): ?User
    {
        return $this->recipientQuery()
            ->where('uuid', $uuid)
            ->first();
    }
}

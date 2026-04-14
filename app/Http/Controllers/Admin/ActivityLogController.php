<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $subjectTypes = $this->subjectTypes();
        $subjectType = $request->string('subject_type')->toString();
        $event = $request->string('event')->toString();
        $causerId = $request->integer('causer_id') ?: null;
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->latest('created_at');

        if ($subjectType !== '' && array_key_exists($subjectType, $subjectTypes)) {
            $query->where('subject_type', $subjectType);
        } else {
            $subjectType = '';
        }

        if ($event !== '') {
            $query->where('event', $event);
        }

        if ($causerId !== null) {
            $query->where('causer_type', User::class)
                ->where('causer_id', $causerId);
        }

        if ($dateFrom instanceof Carbon) {
            $query->whereDate('created_at', '>=', $dateFrom->toDateString());
        }

        if ($dateTo instanceof Carbon) {
            $query->whereDate('created_at', '<=', $dateTo->toDateString());
        }

        $activities = $query
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Activity $activity): array => $this->serializeActivity($activity));

        return Inertia::render('admin/ActivityLog', [
            'activities' => [
                'data' => $activities->items(),
                'links' => [
                    'first' => $activities->url(1),
                    'last' => $activities->url($activities->lastPage()),
                    'prev' => $activities->previousPageUrl(),
                    'next' => $activities->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $activities->currentPage(),
                    'from' => $activities->firstItem(),
                    'last_page' => $activities->lastPage(),
                    'path' => $activities->path(),
                    'per_page' => $activities->perPage(),
                    'to' => $activities->lastItem(),
                    'total' => $activities->total(),
                ],
            ],
            'filters' => [
                'subject_type' => $subjectType !== '' ? $subjectType : null,
                'event' => $event !== '' ? $event : null,
                'causer_id' => $causerId,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
            ],
            'options' => [
                'subject_types' => collect($subjectTypes)
                    ->map(fn (string $label, string $value): array => [
                        'value' => $value,
                        'label' => $label,
                    ])
                    ->values(),
                'events' => Activity::query()
                    ->whereNotNull('event')
                    ->distinct()
                    ->orderBy('event')
                    ->pluck('event')
                    ->values(),
                'causers' => $this->causerOptions(),
            ],
        ]);
    }

    /**
     * @return array<class-string<Model>, string>
     */
    private function subjectTypes(): array
    {
        return [
            User::class => 'User',
            Account::class => 'Account',
            Transaction::class => 'Transaction',
            RecurringEntry::class => 'Recurring entry',
            Category::class => 'Category',
            TrackedItem::class => 'Tracked item',
        ];
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function causerOptions(): Collection
    {
        $causerIds = Activity::query()
            ->where('causer_type', User::class)
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        return User::query()
            ->whereIn('id', $causerIds)
            ->orderBy('email')
            ->get(['id', 'name', 'surname', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'label' => $this->userLabel($user),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeActivity(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'event' => $activity->event,
            'created_at' => $activity->created_at?->toIso8601String(),
            'created_at_human' => $activity->created_at?->diffForHumans(),
            'subject' => [
                'type' => $activity->subject_type,
                'type_label' => $this->subjectTypes()[$activity->subject_type] ?? class_basename((string) $activity->subject_type),
                'id' => $activity->subject_id,
                'label' => $this->subjectLabel($activity),
            ],
            'causer' => [
                'type' => $activity->causer_type,
                'id' => $activity->causer_id,
                'label' => $activity->causer instanceof User
                    ? $this->userLabel($activity->causer)
                    : ($activity->causer_id ? class_basename((string) $activity->causer_type).' #'.$activity->causer_id : 'System'),
            ],
            'changes' => $this->changes($activity),
        ];
    }

    /**
     * @return array<int, array{field: string, old: string|null, new: string|null}>
     */
    private function changes(Activity $activity): array
    {
        $properties = $this->properties($activity);
        $attributes = (array) ($properties['attributes'] ?? []);
        $old = (array) ($properties['old'] ?? []);

        return collect(array_unique([...array_keys($attributes), ...array_keys($old)]))
            ->sort()
            ->map(fn (string $field): array => [
                'field' => $field,
                'old' => array_key_exists($field, $old) ? $this->formatValue($old[$field]) : null,
                'new' => array_key_exists($field, $attributes) ? $this->formatValue($attributes[$field]) : null,
            ])
            ->values()
            ->all();
    }

    private function subjectLabel(Activity $activity): string
    {
        if ($activity->subject instanceof User) {
            return $this->userLabel($activity->subject);
        }

        if ($activity->subject instanceof Account || $activity->subject instanceof Category || $activity->subject instanceof TrackedItem) {
            return (string) $activity->subject->name;
        }

        if ($activity->subject instanceof Transaction) {
            return trim(($activity->subject->description ?: 'Transaction').' '.$activity->subject->amount.' '.$activity->subject->currency);
        }

        if ($activity->subject instanceof RecurringEntry) {
            return $activity->subject->title;
        }

        $attributes = (array) ($this->properties($activity)['attributes'] ?? []);
        $label = $attributes['name']
            ?? $attributes['title']
            ?? $attributes['description']
            ?? $attributes['email']
            ?? null;

        return is_scalar($label) && $label !== ''
            ? (string) $label
            : 'Record #'.$activity->subject_id;
    }

    private function userLabel(User $user): string
    {
        $name = trim(collect([$user->name, $user->surname])->filter()->join(' '));

        return $name !== ''
            ? "{$name} <{$user->email}>"
            : $user->email;
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(Activity $activity): array
    {
        return $activity->properties instanceof Collection
            ? $activity->properties->toArray()
            : (array) $activity->properties;
    }

    private function formatValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}

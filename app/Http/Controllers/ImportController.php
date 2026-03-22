<?php

namespace App\Http\Controllers;

use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Http\Requests\Imports\StoreImportRequest;
use App\Http\Requests\Imports\UpdateImportRowReviewRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportFormat;
use App\Models\ImportRow;
use App\Models\Merchant;
use App\Services\Imports\ApproveDuplicateCandidateRowService;
use App\Services\Imports\DeleteImportService;
use App\Services\Imports\ImportReadyRowsService;
use App\Services\Imports\ProcessGenericCsvImportService;
use App\Services\Imports\ReviewImportRowService;
use App\Services\Imports\RollbackImportService;
use App\Services\Imports\SkipImportRowService;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function __construct(
        protected ManagementContextResolver $managementContextResolver,
        protected ProcessGenericCsvImportService $processGenericCsvImportService,
        protected ImportReadyRowsService $importReadyRowsService,
        protected RollbackImportService $rollbackImportService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $activeYear = $this->managementContextResolver->resolveYearOnly($request, $user);
        $this->managementContextResolver->persist($user, $activeYear, persistMonth: false);
        ImportFormat::ensureGenericCsvV1();
        $statusFilter = (string) $request->string('status')->value();

        $importsQuery = Import::query()
            ->where('user_id', $user->id)
            ->with(['account.bank', 'importFormat'])
            ->withCount('transactions')
            ->where('meta->management_year', $activeYear)
            ->latest()
            ->when($statusFilter !== '' && $statusFilter !== 'all', function ($query) use ($statusFilter): void {
                $query->where('status', $statusFilter);
            });

        $imports = (clone $importsQuery)
            ->paginate(10)
            ->withQueryString();

        $accounts = Account::query()
            ->ownedBy($user->id)
            ->with(['bank', 'userBank'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $formats = ImportFormat::query()
            ->where('status', ImportFormatStatusEnum::ACTIVE)
            ->where('type', ImportFormatTypeEnum::GENERIC_CSV)
            ->with('bank')
            ->orderByDesc('is_generic')
            ->orderBy('name')
            ->get();
        $defaultFormat = $formats->count() === 1 ? $formats->first() : null;

        return Inertia::render('imports/Index', [
            'importsPage' => [
                'active_year' => $activeYear,
                'active_year_label' => __('imports.list.active_year_label', ['year' => $activeYear]),
                'active_year_notice' => __('imports.list.active_year_notice', ['year' => $activeYear]),
                'available_years' => $user->years()
                    ->orderByDesc('year')
                    ->get(['year'])
                    ->map(fn ($year): array => [
                        'value' => $year->year,
                        'label' => (string) $year->year,
                    ])
                    ->values()
                    ->all(),
                'template_download_url' => route('imports.template'),
            ],
            'imports' => [
                'data' => $imports->getCollection()->map(fn (Import $import): array => $this->mapImportListItem($import))->all(),
                'summary' => [
                    'total_count' => (clone $importsQuery)->count(),
                    'review_required_count' => (clone $importsQuery)->where('status', ImportStatusEnum::REVIEW_REQUIRED)->count(),
                    'completed_count' => (clone $importsQuery)->where('status', ImportStatusEnum::COMPLETED)->count(),
                    'failed_count' => (clone $importsQuery)->where('status', ImportStatusEnum::FAILED)->count(),
                ],
                'pagination' => $this->mapPagination($imports),
            ],
            'filters' => [
                'current_status' => $statusFilter !== '' ? $statusFilter : 'all',
                'status_options' => [
                    ['value' => 'all', 'label' => __('imports.list.filters.all')],
                    ['value' => ImportStatusEnum::REVIEW_REQUIRED->value, 'label' => __('imports.list.filters.review_required')],
                    ['value' => ImportStatusEnum::COMPLETED->value, 'label' => __('imports.list.filters.completed')],
                    ['value' => ImportStatusEnum::FAILED->value, 'label' => __('imports.list.filters.failed')],
                    ['value' => ImportStatusEnum::ROLLED_BACK->value, 'label' => __('imports.list.filters.rolled_back')],
                ],
            ],
            'options' => [
                'accounts' => $accounts->map(function (Account $account): array {
                    $bankName = $account->userBank?->name ?? $account->bank?->name;

                    return [
                        'uuid' => $account->uuid,
                        'label' => $bankName !== null ? "{$account->name} · {$bankName}" : $account->name,
                        'name' => $account->name,
                        'bank_name' => $bankName,
                        'currency' => $account->currency,
                    ];
                })->all(),
                'formats' => $formats->map(function (ImportFormat $format): array {
                    return [
                        'uuid' => $format->uuid,
                        'name' => $format->name,
                        'code' => $format->code,
                        'version' => $format->version,
                        'parser_label' => $format->type === ImportFormatTypeEnum::GENERIC_CSV
                            ? __('imports.options.parser_csv')
                            : $format->type->value,
                        'bank_name' => $format->bank?->name,
                        'is_generic' => $format->is_generic,
                        'notes' => $format->notes,
                    ];
                })->all(),
                'default_format_uuid' => $defaultFormat?->uuid,
                'has_single_active_format' => $formats->count() === 1,
            ],
        ]);
    }

    public function store(StoreImportRequest $request): RedirectResponse
    {
        $user = $request->user();
        $activeYear = $this->managementContextResolver->resolveYearOnly($request, $user);
        ImportFormat::ensureGenericCsvV1();
        $validated = $request->validated();
        $format = ImportFormat::query()->findOrFail($validated['import_format_id']);
        $account = Account::query()->ownedBy($user->id)->findOrFail($validated['account_id']);
        $file = $request->file('file');

        $storedFilename = $file->storeAs(
            'imports/'.$user->id,
            now()->format('YmdHis').'-'.Str::random(12).'.'.$file->getClientOriginalExtension(),
            'local'
        );

        $import = Import::query()->create([
            'user_id' => $user->id,
            'bank_id' => $account->bank_id,
            'account_id' => $account->id,
            'import_format_id' => $format->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'mime_type' => $file->getClientMimeType(),
            'source_type' => ImportSourceTypeEnum::CSV,
            'parser_key' => $format->code,
            'status' => ImportStatusEnum::UPLOADED,
            'meta' => [
                'management_year' => $activeYear,
                'import_format_code' => $format->code,
            ],
        ]);

        $processedImport = $this->processGenericCsvImportService->execute($import, $activeYear);

        return to_route('imports.show', ['import' => $processedImport->uuid])
            ->with('success', __('imports.flash.uploaded'));
    }

    public function show(Request $request, Import $import): Response
    {
        abort_unless($import->user_id === $request->user()->id, 404);

        $import->load(['account.bank', 'importFormat.bank', 'rows' => fn ($query) => $query->orderBy('row_index')])
            ->loadCount('transactions');

        $availableAccounts = Account::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name'])
            ->map(fn ($account) => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'label' => $account->name,
            ])
            ->values();

        $availableCategories = Category::query()
            ->ownedBy($request->user()->id)
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $category->name,
                'value' => $category->name,
            ])
            ->values();

        return Inertia::render('imports/Show', [
            'importDetail' => $this->mapImportDetail($import),
            'rows' => $import->rows->map(fn (ImportRow $row): array => $this->mapImportRow($import, $row))->all(),
            'destination_accounts' => $availableAccounts,
            'categories' => $availableCategories,
        ]);
    }

    public function importReady(Request $request, Import $import): RedirectResponse
    {
        abort_unless($import->user_id === $request->user()->id, 404);

        try {
            $importedRowsCountBefore = (int) $import->imported_rows_count;
            $processedImport = $this->importReadyRowsService->execute($import);
            $importedRowsCountAfter = (int) $processedImport->imported_rows_count;
            $importedRowsDelta = max(0, $importedRowsCountAfter - $importedRowsCountBefore);

            if ($importedRowsDelta === 0) {
                return redirect()
                    ->route('imports.show', ['import' => $processedImport->uuid], 303)
                    ->withErrors([
                        'import' => __('imports.flash.import_ready_none'),
                    ]);
            }

            return redirect()
                ->route('imports.show', ['import' => $processedImport->uuid], 303)
                ->with('success', $importedRowsDelta === 1
                    ? __('imports.flash.imported_one')
                    : __('imports.flash.imported_many', ['count' => $importedRowsDelta]));
        } catch (ValidationException $exception) {
            $errors = collect($exception->errors())
                ->flatten()
                ->filter(fn ($message): bool => is_string($message) && $message !== '')
                ->values()
                ->all();
            $message = $errors[0] ?? __('imports.flash.import_ready_failed');

            return redirect()
                ->route('imports.show', ['import' => $import->uuid], 303)
                ->withErrors([
                    'import' => __('imports.flash.import_ready_blocked', ['message' => $message]),
                ]);
        }
    }

    public function rollback(Request $request, Import $import): RedirectResponse
    {
        abort_unless($import->user_id === $request->user()->id, 404);

        $processedImport = $this->rollbackImportService->execute($import);

        return to_route('imports.show', ['import' => $processedImport->uuid])
            ->with('success', __('imports.flash.canceled'));
    }

    public function destroy(
        Request $request,
        Import $import,
        DeleteImportService $deleteImportService,
    ): RedirectResponse {
        abort_unless($import->user_id === $request->user()->id, 404);

        $deleteImportService->execute($import);

        return to_route('imports.index', $request->only(['status', 'page']))
            ->with('success', __('imports.flash.deleted'));
    }

    public function downloadTemplate(Request $request): HttpResponse
    {
        $user = $request->user();
        $activeYear = $this->managementContextResolver->resolveYearOnly($request, $user);
        $headers = [
            __('imports.template.headers.date'),
            __('imports.template.headers.type'),
            __('imports.template.headers.amount'),
            __('imports.template.headers.detail'),
            __('imports.template.headers.category'),
            __('imports.template.headers.reference'),
            __('imports.template.headers.merchant'),
            __('imports.template.headers.external_reference'),
            __('imports.template.headers.balance'),
        ];
        $category = Category::query()
            ->ownedBy($user->id)
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->orderByRaw("CASE WHEN group_type = 'transfer' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->first();
        $incomeCategory = Category::query()
            ->ownedBy($user->id)
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->where('direction_type', 'income')
            ->orderBy('name')
            ->first();
        $merchant = Merchant::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
        $categoryName = $category?->name ?? __('imports.template.default_category');
        $incomeCategoryName = $incomeCategory?->name ?? $categoryName;
        $merchantName = $merchant?->name ?? __('imports.template.default_merchant');

        $exampleRows = [
            [
                sprintf('15/03/%d', $activeYear),
                __('imports.template.expense_type'),
                '18,50',
                $merchant?->name ? 'Pagamento '.$merchant->name : __('imports.template.expense_detail'),
                $categoryName,
                'RIF-001',
                $merchantName,
                'EXT-001',
                '980,40',
            ],
            [
                sprintf('28/03/%d', $activeYear),
                __('imports.template.income_type'),
                '125,00',
                __('imports.template.income_detail'),
                $incomeCategoryName,
                'RIF-002',
                $merchantName,
                'EXT-002',
                '1105,40',
            ],
        ];

        $content = implode(';', $headers).PHP_EOL.collect($exampleRows)
            ->map(fn (array $row): string => implode(';', $row))
            ->implode(PHP_EOL).PHP_EOL;

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.__('imports.template.filename').'"',
        ]);
    }

    protected function mapImportListItem(Import $import): array
    {
        return [
            'uuid' => $import->uuid,
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'status_tone' => $this->importStatusTone($import->status),
            'original_filename' => $import->original_filename,
            'account_name' => $import->account?->name,
            'bank_name' => $import->account?->bank?->name,
            'format_name' => $import->importFormat?->name,
            'parser_label' => $this->formatParserLabel($import),
            'imported_at_label' => optional($import->created_at)?->format('d/m/Y H:i'),
            'rows_count' => $import->rows_count,
            'ready_rows_count' => $import->ready_rows_count,
            'review_rows_count' => $import->review_rows_count,
            'invalid_rows_count' => $import->invalid_rows_count,
            'duplicate_rows_count' => $import->duplicate_rows_count,
            'management_year' => $this->managementYear($import),
            'management_year_label' => __('imports.list.active_year_label', ['year' => $this->managementYear($import)]),
            'show_url' => route('imports.show', ['import' => $import->uuid]),
            'can_delete' => $this->canDeleteImport($import),
            'delete_url' => $this->canDeleteImport($import)
                ? route('imports.destroy', ['import' => $import->uuid])
                : null,
        ];
    }

    protected function mapImportDetail(Import $import): array
    {
        return array_merge($this->mapImportListItem($import), [
            'parser_key' => $import->parser_key,
            'error_message' => $import->error_message,
            'meta' => $import->meta,
            'completed_at_label' => optional($import->completed_at)?->format('d/m/Y H:i'),
            'failed_at_label' => optional($import->failed_at)?->format('d/m/Y H:i'),
            'rolled_back_at_label' => optional($import->rolled_back_at)?->format('d/m/Y H:i'),
            'blocked_year_rows_count' => $import->rows->where('status', ImportRowStatusEnum::BLOCKED_YEAR)->count(),
            'imported_rows_count' => $import->imported_rows_count,
            'can_import_ready' => $import->status !== ImportStatusEnum::ROLLED_BACK && $import->ready_rows_count > 0,
            'can_rollback' => $import->status !== ImportStatusEnum::ROLLED_BACK && $import->imported_rows_count > 0,
        ]);
    }

    protected function mapImportRow(Import $import, ImportRow $row): array
    {
        $normalizedPayload = $row->normalized_payload ?? [];
        $rawPayload = $row->raw_payload ?? [];
        $reviewValues = [
            'date' => $rawPayload['date'] ?? $normalizedPayload['date'] ?? null,
            'type' => $rawPayload['type'] ?? $this->normalizedTypeLabel($normalizedPayload),
            'amount' => $rawPayload['amount'] ?? $row->raw_amount,
            'detail' => $rawPayload['detail'] ?? $normalizedPayload['detail'] ?? $row->raw_description,
            'category' => $rawPayload['category'] ?? $normalizedPayload['category'] ?? null,
            'reference' => $rawPayload['reference'] ?? $normalizedPayload['reference'] ?? null,
            'merchant' => $rawPayload['merchant'] ?? $normalizedPayload['merchant'] ?? null,
            'external_reference' => $rawPayload['external_reference'] ?? $normalizedPayload['external_reference'] ?? null,
            'balance' => $rawPayload['balance'] ?? $row->raw_balance ?? $normalizedPayload['balance'] ?? null,
            'destination_account_id' => $normalizedPayload['destination_account_id'] ?? null,
            'destination_account_uuid' => $normalizedPayload['destination_account_uuid'] ?? null,
        ];
        $canEditReview = in_array($row->status, [
            ImportRowStatusEnum::NEEDS_REVIEW,
            ImportRowStatusEnum::INVALID,
            ImportRowStatusEnum::BLOCKED_YEAR,
            ImportRowStatusEnum::DUPLICATE_CANDIDATE,
        ], true);
        $canSkip = in_array($row->status, [
            ImportRowStatusEnum::READY,
            ImportRowStatusEnum::NEEDS_REVIEW,
            ImportRowStatusEnum::INVALID,
            ImportRowStatusEnum::BLOCKED_YEAR,
            ImportRowStatusEnum::DUPLICATE_CANDIDATE,
        ], true);

        return [
            'uuid' => $row->uuid,
            'row_index' => $row->row_index,
            'status' => $row->status->value,
            'status_label' => $row->status->label(),
            'status_tone' => $this->rowStatusTone($row->status),
            'parse_status' => $row->parse_status->value,
            'parse_status_label' => $row->parse_status->label(),
            'description' => $row->raw_description,
            'amount' => $row->raw_amount,
            'date' => $row->raw_date,
            'type_label' => $this->normalizedTypeLabel($normalizedPayload),
            'category_label' => $normalizedPayload['category'] ?? null,
            'is_ready' => $row->status === ImportRowStatusEnum::READY,
            'is_imported' => $row->status === ImportRowStatusEnum::IMPORTED,
            'is_blocked' => in_array($row->status, [ImportRowStatusEnum::INVALID, ImportRowStatusEnum::BLOCKED_YEAR], true),
            'can_edit_review' => $canEditReview,
            'can_skip' => $canSkip,
            'review_values' => $reviewValues,
            'review_update_url' => route('imports.rows.update-review', ['import' => $import->uuid, 'row' => $row->uuid]),
            'skip_url' => route('imports.rows.skip', ['import' => $import->uuid, 'row' => $row->uuid]),
            'approve_duplicate_url' => $row->status === ImportRowStatusEnum::DUPLICATE_CANDIDATE
                ? route('imports.rows.approve-duplicate', [
                    'import' => $import->uuid,
                    'row' => $row->uuid,
                ])
                : null,
            'errors' => $row->errors ?? [],
            'warnings' => $row->warnings ?? [],
            'raw_payload' => $this->mapPayloadForDisplay($rawPayload),
            'normalized_payload' => $this->mapPayloadForDisplay($normalizedPayload),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapPagination(LengthAwarePaginator $paginator): array
    {
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $startPage = max(1, $currentPage - 2);
        $endPage = min($lastPage, $currentPage + 2);

        return [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_pages' => $paginator->hasPages(),
            'previous_page_url' => $paginator->previousPageUrl(),
            'next_page_url' => $paginator->nextPageUrl(),
            'pages' => collect(range($startPage, max($startPage, $endPage)))
                ->filter(fn (int $page): bool => $page <= $lastPage)
                ->map(fn (int $page): array => [
                    'label' => (string) $page,
                    'url' => $paginator->url($page),
                    'active' => $page === $currentPage,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, string|null>>
     */
    protected function mapPayloadForDisplay(array $payload): array
    {
        $labels = [
            'date' => __('imports.template.payload_labels.date'),
            'type' => __('imports.template.payload_labels.type'),
            'amount' => __('imports.template.payload_labels.amount'),
            'detail' => __('imports.template.payload_labels.detail'),
            'category' => __('imports.template.payload_labels.category'),
            'reference' => __('imports.template.payload_labels.reference'),
            'merchant' => __('imports.template.payload_labels.merchant'),
            'external_reference' => __('imports.template.payload_labels.external_reference'),
            'balance' => __('imports.template.payload_labels.balance'),
            'destination_account_id' => __('imports.template.payload_labels.destination_account_id'),
            'destination_account_uuid' => __('imports.template.payload_labels.destination_account_uuid'),
        ];

        return collect($payload)
            ->map(fn ($value, $key): array => [
                'key' => (string) $key,
                'label' => $labels[$key] ?? Str::headline((string) $key),
                'value' => is_scalar($value) || $value === null ? ($value !== null ? (string) $value : null) : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])
            ->values()
            ->all();
    }

    protected function importStatusTone(ImportStatusEnum $status): string
    {
        return match ($status) {
            ImportStatusEnum::COMPLETED => 'success',
            ImportStatusEnum::FAILED => 'danger',
            ImportStatusEnum::REVIEW_REQUIRED => 'warning',
            ImportStatusEnum::ROLLED_BACK => 'muted',
            default => 'info',
        };
    }

    protected function rowStatusTone(ImportRowStatusEnum $status): string
    {
        return match ($status) {
            ImportRowStatusEnum::READY,
            ImportRowStatusEnum::IMPORTED => 'success',
            ImportRowStatusEnum::NEEDS_REVIEW,
            ImportRowStatusEnum::DUPLICATE_CANDIDATE,
            ImportRowStatusEnum::ALREADY_IMPORTED => 'warning',
            ImportRowStatusEnum::BLOCKED_YEAR => 'danger',
            ImportRowStatusEnum::ROLLED_BACK,
            ImportRowStatusEnum::SKIPPED => 'muted',
            default => 'danger',
        };
    }

    protected function formatParserLabel(Import $import): string
    {
        if ($import->importFormat?->type === ImportFormatTypeEnum::GENERIC_CSV) {
            return __('imports.options.parser_csv');
        }

        return __('imports.options.parser_file');
    }

    protected function managementYear(Import $import): int
    {
        return (int) ($import->meta['management_year'] ?? now()->year);
    }

    protected function canDeleteImport(Import $import): bool
    {
        return $import->status === ImportStatusEnum::ROLLED_BACK
            && (int) ($import->transactions_count ?? $import->transactions()->count()) === 0;
    }

    /**
     * @param  array<string, mixed>  $normalizedPayload
     */
    protected function normalizedTypeLabel(array $normalizedPayload): ?string
    {
        $type = $normalizedPayload['type'] ?? null;

        return match ($type) {
            'income' => __('imports.enums.normalized_type.income'),
            'expense' => __('imports.enums.normalized_type.expense'),
            'bill' => __('imports.enums.normalized_type.bill'),
            'debt' => __('imports.enums.normalized_type.debt'),
            'saving' => __('imports.enums.normalized_type.saving'),
            'transfer' => __('imports.enums.normalized_type.transfer'),
            default => null,
        };
    }

    public function updateRowReview(
        UpdateImportRowReviewRequest $request,
        Import $import,
        ImportRow $row,
        ReviewImportRowService $service,
    ): RedirectResponse {
        abort_unless($import->user_id === $request->user()->id, 403);
        abort_unless($row->import_id === $import->id, 404);

        $service->execute($import, $row, $request->validated());

        return back()->with('success', __('imports.flash.row_saved'));
    }

    public function skipRow(
        Request $request,
        Import $import,
        ImportRow $row,
        SkipImportRowService $service,
    ): RedirectResponse {
        abort_unless($import->user_id === $request->user()->id, 403);
        abort_unless($row->import_id === $import->id, 404);

        $service->execute($import, $row);

        return back()->with('success', __('imports.flash.row_skipped'));
    }

    public function approveDuplicateRow(
        Request $request,
        Import $import,
        ImportRow $row,
        ApproveDuplicateCandidateRowService $service,
    ) {
        $this->authorizeImportAccess($request, $import, $row);

        $service->execute($import, $row);

        return redirect()
            ->route('imports.show', $import->uuid)
            ->with('success', __('imports.flash.duplicate_approved'));
    }

    protected function authorizeImportAccess(Request $request, Import $import, ?ImportRow $row = null): void
    {
        abort_unless($import->user_id === $request->user()->id, 403);

        if ($row !== null) {
            abort_unless($row->import_id === $import->id, 404);
        }
    }
}

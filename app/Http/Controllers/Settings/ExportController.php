<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ExportDatasetEnum;
use App\Enums\ExportFormatEnum;
use App\Enums\ExportPeriodPresetEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DownloadExportRequest;
use App\Models\User;
use App\Services\Exports\ExportDownloadResponseFactory;
use App\Services\Exports\UserDataExportManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected UserDataExportManager $exportManager,
        protected ExportDownloadResponseFactory $downloadResponseFactory,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Export', [
            'exportPage' => [
                'datasets' => collect(ExportDatasetEnum::cases())
                    ->map(fn (ExportDatasetEnum $dataset): array => [
                        'key' => $dataset->value,
                        'supports_period' => $dataset->supportsPeriod(),
                        'formats' => $dataset->availableFormatValues(),
                        'default_format' => $dataset->defaultFormat()->value,
                    ])
                    ->values()
                    ->all(),
                'period_presets' => collect(ExportPeriodPresetEnum::cases())
                    ->map(fn (ExportPeriodPresetEnum $preset): array => [
                        'key' => $preset->value,
                    ])
                    ->values()
                    ->all(),
                'defaults' => [
                    'dataset' => ExportDatasetEnum::TRANSACTIONS->value,
                    'format' => ExportFormatEnum::CSV->value,
                    'period_preset' => ExportPeriodPresetEnum::THIS_MONTH->value,
                ],
            ],
        ]);
    }

    public function download(DownloadExportRequest $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dataset = $request->dataset();
        $format = $request->exportFormat();
        $period = $this->exportManager->normalizePeriod($dataset, $request->period());
        $filename = $this->exportManager->filenameFor($dataset, $format, $period);

        if ($format === ExportFormatEnum::CSV) {
            return $this->downloadResponseFactory->csv(
                $filename,
                $this->exportManager->csvHeadersFor($dataset),
                $this->exportManager->recordsFor($user, $dataset, $period),
            );
        }

        return $this->downloadResponseFactory->json(
            $filename,
            $this->exportManager->jsonPayloadFor($user, $dataset, $period),
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchEntriesRequest;
use App\Services\Search\UniversalEntrySearchService;
use Illuminate\Http\JsonResponse;

class EntrySearchController extends Controller
{
    public function __construct(
        protected UniversalEntrySearchService $universalEntrySearchService,
    ) {}

    public function index(SearchEntriesRequest $request): JsonResponse
    {
        return response()->json(
            $this->universalEntrySearchService->search(
                $request->user(),
                $request->validated(),
            ),
        );
    }
}

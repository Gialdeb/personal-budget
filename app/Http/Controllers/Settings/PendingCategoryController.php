<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Categories\PendingCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingCategoryController extends Controller
{
    public function effects(Request $request, PendingCategoryService $categories): JsonResponse
    {
        $request->validate(['category_uuid' => ['required', 'string', 'max:30000'], 'account_uuid' => ['nullable', 'uuid'], 'account_id' => ['nullable', 'integer']]);

        return response()->json($categories->effects($request, $request->input('category_uuid')));
    }
}

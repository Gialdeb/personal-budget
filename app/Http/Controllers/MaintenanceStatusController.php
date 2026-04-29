<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MaintenanceStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $active = app()->isDownForMaintenance();

        return response()->json([
            'active' => $active,
            'status' => $active ? 'active' : 'inactive',
            'checked_at' => now()->toJSON(),
        ]);
    }
}

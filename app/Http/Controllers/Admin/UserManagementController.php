<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminUserManagementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        protected AdminUserManagementService $userManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->userManagementService->normalizeFilters($request->only([
            'search',
            'role',
            'status',
            'plan',
        ]));

        return Inertia::render('admin/Users', [
            'users' => $this->userManagementService->paginateUsers($filters),
            'filters' => $filters,
            'options' => $this->userManagementService->filterOptions(),
        ]);
    }
}

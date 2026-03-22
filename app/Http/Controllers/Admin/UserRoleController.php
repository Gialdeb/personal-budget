<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\User;
use App\Services\Admin\AdminUserActionService;
use Illuminate\Http\RedirectResponse;

class UserRoleController extends Controller
{
    public function __construct(
        protected AdminUserActionService $adminUserActionService,
    ) {}

    public function update(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $this->adminUserActionService->syncRoles(
            $request->user(),
            $user,
            $request->validated('roles'),
        );

        return back()->with('success', __('admin.users.flash.roles_updated'));
    }
}

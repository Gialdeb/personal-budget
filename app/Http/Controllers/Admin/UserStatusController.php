<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\Admin\AdminUserActionService;
use Illuminate\Http\RedirectResponse;

class UserStatusController extends Controller
{
    public function __construct(
        protected AdminUserActionService $adminUserActionService,
    ) {}

    public function ban(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $this->adminUserActionService->banUser(
            $request->user(),
            $user,
            $request->validated('reason'),
        );

        return back()->with('success', __('admin.users.flash.banned'));
    }

    public function suspend(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $this->adminUserActionService->suspendUser(
            $request->user(),
            $user,
            $request->validated('reason'),
        );

        return back()->with('success', __('admin.users.flash.suspended'));
    }

    public function reactivate(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $this->adminUserActionService->reactivateUser(
            $request->user(),
            $user,
        );

        return back()->with('success', __('admin.users.flash.reactivated'));
    }
}

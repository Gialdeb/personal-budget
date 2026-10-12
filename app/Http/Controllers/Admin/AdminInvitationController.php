<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminInvitationRequest;
use App\Models\AdminInvitation;
use App\Models\User;
use App\Services\Admin\AdminInvitationService;
use Illuminate\Http\RedirectResponse;

class AdminInvitationController extends Controller
{
    public function store(StoreAdminInvitationRequest $request, User $user, AdminInvitationService $service): RedirectResponse
    {
        $service->invite($request->user(), $user);

        return back()->with('success', __('admin_invitation.sent'));
    }

    public function destroy(StoreAdminInvitationRequest $request, AdminInvitation $adminInvitation, AdminInvitationService $service): RedirectResponse
    {
        $service->cancel($request->user(), $adminInvitation);

        return back()->with('success', __('admin_invitation.cancelled'));
    }
}

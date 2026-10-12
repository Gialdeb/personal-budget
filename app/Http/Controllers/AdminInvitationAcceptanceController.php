<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptAdminInvitationRequest;
use App\Models\AdminInvitation;
use App\Services\Admin\AdminInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminInvitationAcceptanceController extends Controller
{
    public function show(Request $request, AdminInvitation $adminInvitation): Response
    {
        abort_unless($adminInvitation->target_user_id === $request->user()->id && ! app('impersonate')->isImpersonating(), 404);

        return Inertia::render('auth/AdminInvitation', [
            'invitation' => ['uuid' => $adminInvitation->uuid, 'pending' => $adminInvitation->isPending(), 'expires_at' => $adminInvitation->expires_at->toIso8601String()],
        ]);
    }

    public function store(AcceptAdminInvitationRequest $request, AdminInvitation $adminInvitation, AdminInvitationService $service): RedirectResponse
    {
        $service->accept($request->user(), $adminInvitation, $request->validated('otp'));
        $request->session()->regenerate(true);
        Inertia::clearHistory();

        return to_route('admin.users')->with('success', __('admin_invitation.accepted'));
    }
}

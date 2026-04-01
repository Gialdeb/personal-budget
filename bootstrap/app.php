<?php

use App\Exceptions\CannotInviteToAccountException;
use App\Exceptions\CannotLeaveAccountMembershipException;
use App\Exceptions\CannotRegisterFromAccountInvitationException;
use App\Exceptions\CannotRestoreAccountMembershipException;
use App\Exceptions\CannotRevokeAccountMembershipException;
use App\Exceptions\InvalidAccountInvitationException;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetApplicationLocaleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'webhooks/kofi',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'not_banned' => EnsureUserIsNotBanned::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetApplicationLocaleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $renderSharingException = function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'sharing' => [$e->getMessage()],
                    ],
                ], 422);
            }

            return null;
        };

        $exceptions->render(fn (CannotInviteToAccountException $e, Request $request) => $renderSharingException($e, $request));
        $exceptions->render(fn (InvalidAccountInvitationException $e, Request $request) => $renderSharingException($e, $request));
        $exceptions->render(fn (CannotLeaveAccountMembershipException $e, Request $request) => $renderSharingException($e, $request));
        $exceptions->render(fn (CannotRevokeAccountMembershipException $e, Request $request) => $renderSharingException($e, $request));
        $exceptions->render(fn (CannotRestoreAccountMembershipException $e, Request $request) => $renderSharingException($e, $request));
        $exceptions->render(fn (CannotRegisterFromAccountInvitationException $e, Request $request) => $renderSharingException($e, $request));
    })->create();

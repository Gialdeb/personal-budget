<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionIdentityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['user_uuid' => $request->user()?->uuid]);
    }
}

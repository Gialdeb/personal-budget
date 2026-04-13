<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicEditorialAssetController extends Controller
{
    public function __invoke(Request $request)
    {
        $path = (string) $request->query('path', '');

        abort_unless(
            str_starts_with($path, 'editorial/rich-content/'),
            404,
        );

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}

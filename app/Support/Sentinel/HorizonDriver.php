<?php

namespace App\Support\Sentinel;

use Illuminate\Http\Request;
use Laravel\Sentinel\Drivers\Driver;

class HorizonDriver extends Driver
{
    /**
     * Let Horizon rely on its own middleware chain for authorization.
     */
    public function authorize(Request $request): bool
    {
        return true;
    }
}

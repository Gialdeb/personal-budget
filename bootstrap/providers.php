<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Lab404\Impersonate\ImpersonateServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ImpersonateServiceProvider::class,
];

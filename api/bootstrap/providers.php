<?php

use App\Providers\AppServiceProvider;
use App\Providers\BusinessServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

$providers = [
    AppServiceProvider::class,
    BusinessServiceProvider::class,
];

if (env('APP_ENV') === 'local' && class_exists(TelescopeServiceProvider::class)) {
    $providers[] = App\Providers\TelescopeServiceProvider::class;
}

return $providers;

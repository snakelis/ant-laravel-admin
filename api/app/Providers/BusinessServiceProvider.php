<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/** 业务项目维护：在这里注册业务服务、Policy 和事件监听器。 */
class BusinessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // $this->app->bind(...);
    }

    public function boot(): void
    {
        // Gate::policy(Customer::class, CustomerPolicy::class);
    }
}

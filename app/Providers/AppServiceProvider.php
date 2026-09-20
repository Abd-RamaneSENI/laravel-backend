<?php

namespace App\Providers;

use App\Models\Resource;
use App\Policies\ResourcePolicy;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayFactory::class);
        $this->app->singleton(PaymentService::class);
    }

    public function boot(): void
    {
        Gate::policy(Resource::class, ResourcePolicy::class);
        Gate::define('admin', fn ($user) => $user->isAdmin());
        Gate::define('vendor', fn ($user) => $user->isVendor());
    }
}

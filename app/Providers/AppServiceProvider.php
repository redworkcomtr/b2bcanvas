<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\ProductMapping;
use App\Models\User;
use App\Policies\IssuePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductMappingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(ProductMapping::class, ProductMappingPolicy::class);

        Gate::define('create-issue', [IssuePolicy::class, 'create']);
    }
}

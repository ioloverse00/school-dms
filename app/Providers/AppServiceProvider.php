<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

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
        if (view()->exists('vendor.pagination.windmill')) {
            Paginator::defaultView('vendor.pagination.windmill');
            Paginator::defaultSimpleView('vendor.pagination.windmill');
        }
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use App\Services\MenuService;
use App\Models\Operation;
use App\Models\Zone;
use App\Policies\OperationPolicy;
use App\Policies\ZonePolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        Paginator::useBootstrapFive();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Operation::class, OperationPolicy::class);
        Gate::policy(Zone::class, ZonePolicy::class);

        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });

        View::composer('*', function ($view) {

            $menus = app(MenuService::class)->get();

            $view->with('menus', $menus);
        });
    }
}

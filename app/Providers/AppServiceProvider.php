<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use App\Services\MenuService;
use App\Models\Operation;
use App\Models\Organization;
use App\Models\Zone;
use App\Policies\OrganizationPolicy;
use App\Policies\OperationPolicy;
use App\Policies\ZonePolicy;
use App\Policies\UserPolicy;

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
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Zone::class, ZonePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });

        View::composer('*', function ($view) {

            $menus = app(MenuService::class)->get();

            $view->with('menus', $menus);
        });
    }
}

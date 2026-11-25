<?php

namespace App\Providers;

use App\Contracts\MaterialInClient;
use App\Contracts\VsmClient;
use App\Models\Ncr;
use App\Policies\NcrPolicy;
use App\Services\MaterialIn\FakeMaterialInClient;
use App\Services\MaterialIn\HttpMaterialInClient;
use App\Services\Vsm\FakeVsmClient;
use App\Services\Vsm\HttpVsmClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VsmClient::class, function (Application $app): VsmClient {
            return match ($app['config']->get('vsm.driver')) {
                'fake' => $app->make(FakeVsmClient::class),
                'http' => $app->make(HttpVsmClient::class),
                default => throw new InvalidArgumentException('Unsupported VSM driver.'),
            };
        });

        $this->app->bind(MaterialInClient::class, function (Application $app): MaterialInClient {
            return match ($app['config']->get('material_in.driver')) {
                'fake' => $app->make(FakeMaterialInClient::class),
                'http' => $app->make(HttpMaterialInClient::class),
                default => throw new InvalidArgumentException('Unsupported MaterialIn driver.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('Support/helpers.php');

        View::composer('layouts.module', function (\Illuminate\View\View $view): void {
            $unreadNotificationsCount = 0;

            if (Auth::check()) {
                try {
                    $unreadNotificationsCount = Auth::user()->unreadNotifications()->count();
                } catch (\Throwable) {
                    $unreadNotificationsCount = 0;
                }
            }

            $view->with('unreadNotificationsCount', $unreadNotificationsCount);
        });

        Gate::policy(Ncr::class, NcrPolicy::class);

        Paginator::defaultView('vendor.pagination.mudalaya');
    }
}

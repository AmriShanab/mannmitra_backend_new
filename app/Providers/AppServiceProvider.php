<?php

namespace App\Providers;

use App\Interfaces\SessionRepositaryInterface;
use App\Interfaces\UserRepositaryInterface;
use App\Repositaries\SessionRepositary;
use App\Repositaries\UserRepositary;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositaryInterface::class, UserRepositary::class);
        $this->app->bind(SessionRepositaryInterface::class, SessionRepositary::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

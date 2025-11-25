<?php

namespace App\Providers;

use App\Interfaces\JournalRepositoryInterface;
use App\Interfaces\MoodRepositoryInterface;
use App\Interfaces\SessionRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Repositories\JournalRepository;
use App\Repositories\MoodRepositary;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(SessionRepositoryInterface::class, SessionRepository::class);
        $this->app->bind(MoodRepositoryInterface::class, MoodRepositary::class);
        $this->app->bind(JournalRepositoryInterface::class, JournalRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

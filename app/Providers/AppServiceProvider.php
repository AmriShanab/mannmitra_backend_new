<?php

namespace App\Providers;

use App\Interfaces\AdminRepositoryInterface;
use App\Repositories\CrisisAlertRepository;
use App\Interfaces\CrisisRepositoryInterface;
use App\Interfaces\JournalRepositoryInterface;
use App\Interfaces\MessageRepositoryInterface;
use App\Interfaces\MoodRepositoryInterface;
use App\Interfaces\SessionRepositoryInterface;
use App\Interfaces\SubscriptionRespositoryInterface;
use App\Interfaces\TicketRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Repositories\AdminRepository;
use App\Repositories\JournalRepository;
use App\Repositories\MessageRepository;
use App\Repositories\MoodRepositary;
use App\Repositories\SessionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Gate;
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
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        $this->app->bind(CrisisRepositoryInterface::class, CrisisAlertRepository::class);
        $this->app->bind(AdminRepositoryInterface::class, AdminRepository::class);
        $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
        $this->app->bind(SubscriptionRespositoryInterface::class, SubscriptionRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 2. Define the "admin-access" rule
        Gate::define('admin-access', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('listener-access', function (User $user) {
            // Allow if role is 'listener' OR 'admin'
            return in_array($user->role, ['listener', 'admin']);
        });

        Gate::define('psychiatrist-access', function (User $user) {
            return in_array($user->role, ['doctor', 'admin']);
        });
    }
}

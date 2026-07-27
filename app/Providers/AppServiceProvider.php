<?php

namespace App\Providers;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Login::class, function (Login $event) {
            ActivityLog::create([
                'user_id'    => $event->user->id,
                'user_name'  => $event->user->name,
                'action'     => 'login',
                'model_type' => 'User',
                'model_id'   => $event->user->id,
                'ip_address' => request()?->ip(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            if (!$event->user) {
                return;
            }

            ActivityLog::create([
                'user_id'    => $event->user->id,
                'user_name'  => $event->user->name,
                'action'     => 'logout',
                'model_type' => 'User',
                'model_id'   => $event->user->id,
                'ip_address' => request()?->ip(),
            ]);
        });
    }
}

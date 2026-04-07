<?php

namespace App\Providers;

use Acl\Communications\Listeners\NotificationListener;
use App\Events\RequestCreated;
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
        Event::listen(RequestCreated::class, NotificationListener::class);
    }
}

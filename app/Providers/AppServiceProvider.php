<?php

namespace App\Providers;

use App\Events\UserRegistered;
use App\Listeners\SendEmailVerification;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * SendEmailVerification is registered manually because Laravel's
         * event auto-discovery does not pick it up (likely due to its
         * constructor injection). All other listeners (e.g. SendNotification)
         * are handled by auto-discovery and must NOT be registered here to
         * avoid double-firing.
         */
        Event::listen(UserRegistered::class, SendEmailVerification::class);

        Scramble::configure()->routes(function (Route $route) {
            return Str::startsWith($route->uri, 'api/');
        });
    }
}

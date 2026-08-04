<?php

namespace App\Providers;

use App\Events\ScorePublished;
use App\Events\SubmissionCreated;
use App\Listeners\CalculateScore;
use App\Listeners\SendNotification;
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
        Event::listen(SubmissionCreated::class, CalculateScore::class);
        Event::listen(ScorePublished::class, SendNotification::class);

        Scramble::configure()->routes(function (Route $route) {
            return Str::startsWith($route->uri, 'api/');
        });
    }
}

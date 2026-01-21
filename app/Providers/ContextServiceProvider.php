<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class ContextServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }
    public function boot(): void
    {
        Request::macro('ctx', function () {
            return $this->attributes->get('ctx');
        });

        Request::macro('uid', function () {
            return $this->attributes->get('ctx')['uid'] ?? null;
        });

        Request::macro('sid', function () {
            return $this->attributes->get('ctx')['sid'] ?? null;
        });

        Request::macro('rid', function () {
            return $this->attributes->get('ctx')['rid'] ?? null;
        });
    }
}

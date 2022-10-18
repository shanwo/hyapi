<?php

namespace App\Providers;

use App\Exceptions\RedisUserProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make('auth')->provider('redis', function ($app, $config) {
            return new RedisUserProvider($app['hash'], $config['model']);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

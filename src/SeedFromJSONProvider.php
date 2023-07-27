<?php

namespace bluekachina\seedfromjson;

use Illuminate\Support\ServiceProvider;

class SeedFromJSONProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //$this->app->make('bluekachina\seedfromjson\SeedFromJSON.php');
        $configPath = __DIR__ . '/config/seedfromjson.php';
        $this->mergeConfigFrom($configPath, 'seedfromjson');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
//        $this->loadRoutesFrom(__DIR__.'/routes.php');
//        $this->loadMigrationsFrom(__DIR__.'/migrations');
//        $this->loadViewsFrom(__DIR__.'/views', 'seedfromjson');
        $this->publishes([
            __DIR__.'/config/seedfromjson.php' => config_path('seedfromjson.php'),
        ], 'config');
    }
}

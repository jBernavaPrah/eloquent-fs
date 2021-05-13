<?php

namespace FidesAds\GridFS;

use Illuminate\Support\ServiceProvider;

class GridFSServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

        $this->registerMigrations();
        $this->registerPublishing();

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

        $this->configure();

    }

    /**
     * Setup the configuration for GridFS.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/gridfs.php', 'gridfs'
        );
    }

    protected function registerMigrations()
    {
        if (GridFs::$runsMigrations && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/gridfs.php' => $this->app->configPath('gridfs.php'),
            ], 'gridfs-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'gridfs-migrations');

        }
    }

}

<?php


namespace JBernavaPrah\EloquentFS;


use Illuminate\Support\ServiceProvider;

class EloquentFSServiceProvider extends ServiceProvider
{

    public function register()
    {

    }

    public function boot()
    {

        if (EloquentFS::$runMigrations) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        EloquentFSStreamWrapper::register();

        $this->registerPublishing();

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
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'eloquent-fs-migrations');

        }
    }

}
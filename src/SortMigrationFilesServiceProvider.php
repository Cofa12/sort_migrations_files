<?php

namespace Cofa\SortMigrationsFiles;


use Illuminate\Support\ServiceProvider;

class SortMigrationFilesServiceProvider extends ServiceProvider
{
    public function register()
    {

    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([

            ]);
        }
    }
}
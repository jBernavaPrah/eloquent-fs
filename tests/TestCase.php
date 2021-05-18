<?php

namespace JBernavaPrah\EloquentFS\Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use JBernavaPrah\EloquentFS\EloquentFSServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{

    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [
            EloquentFSServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {

    }
}
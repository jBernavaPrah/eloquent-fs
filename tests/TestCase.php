<?php

namespace Tests;


use JBernavaPrah\EloquentFS\EloquentFSStreamWrapper;
use Illuminate\Database\Capsule\Manager;
use PHPUnit\Framework\TestCase as BaseTestCAse;

class TestCase extends BaseTestCAse
{
    /**
     * Create the tables this model needs for testing.
     */
    public function setUp(): void
    {
        parent::setUp();


        $db = new Manager();

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'default');

        $db->setAsGlobal();
        $db->bootEloquent();

        EloquentFSStreamWrapper::migrate($db);
        EloquentFSStreamWrapper::register();

    }

}


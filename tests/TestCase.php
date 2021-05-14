<?php

namespace Tests;


use JBernavaPrah\EloquentFS\Migration;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\TestCase as BaseTestCAse;

class TestCase extends BaseTestCAse
{
    /**
     * Create the tables this model needs for testing.
     */
    public function setUp(): void
    {
        parent::setUp();

        $db = new DB();

        $config = require __DIR__ . '/config/database.php';

        $db->addConnection($config[getenv('DATABASE') ?: 'sqlite']);
        $db->setAsGlobal();
        $db->bootEloquent();

        $this->migrate();

    }

    public function migrate()
    {
        DB::schema()->dropAllTables();

        (new Migration())->up();

    }
}


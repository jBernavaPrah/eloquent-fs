<?php

namespace FidesAds\GridFS\Tests;


use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\TestCase as BaseTestCAse;

class TestCase extends BaseTestCAse
{
    /**
     * Create the tables this model needs for testing.
     */
    public function setUp() : void
    {
        parent::setUp();

        $db = new DB();

        $config = require __DIR__.'/config/database.php';

        $db->addConnection($config[getenv('DATABASE') ?: 'sqlite']);
        $db->setAsGlobal();
        $db->bootEloquent();

        $this->migrate();

    }

    public function migrate(){
        DB::schema()->dropAllTables();

        $migration = require __DIR__ .'/../database/migrations/2021_05_12_094604_create_files_table.php';
        $migration->up();

        $migration = require __DIR__ .'/../database/migrations/2021_05_12_094622_create_file_chunks_table.php';
        $migration->up();

    }
}


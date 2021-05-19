<?php


namespace JBernavaPrah\EloquentFS\Traits;


use JBernavaPrah\EloquentFS\EloquentFS;

trait HasDynamicConnection
{

    function initializeHasDynamicConnection()
    {
        $this->connection = EloquentFS::$connection;
    }

}
<?php


namespace JBernavaPrah\EloquentFS\Exception;


class RuntimeException extends \RuntimeException
{

    /**
     * Thrown when try to change chunk size to file that is already written on db.
     *
     * @return self
     */
    public static function invalidChangeOfChunkSize(): RuntimeException
    {
        return new static('Change size of chunk should not be done after a file is already written. ');
    }

}
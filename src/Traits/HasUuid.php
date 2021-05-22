<?php


namespace JBernavaPrah\EloquentFS\Traits;


use Illuminate\Support\Str;

trait HasUuid
{

    public function initializeHasUuid()
    {

        if (!$this->{$this->getKeyName()}) {
            $this->{$this->getKeyName()} = $this->{$this->getKeyName()} ?: (string)Str::orderedUuid();
        }

    }

    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }
}
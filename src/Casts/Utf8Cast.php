<?php


namespace JBernavaPrah\EloquentFS\Casts;


use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Utf8Cast implements CastsAttributes
{

    /**
     * @inheritDoc
     */
    public function set($model, string $key, $value, array $attributes)
    {
        return utf8_encode($value);
    }

    public function get($model, string $key, $value, array $attributes)
    {
        return utf8_decode($value);
    }
}
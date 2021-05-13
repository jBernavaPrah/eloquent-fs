<?php


namespace FidesAds\GridFS\Concerns;


interface StreamableWrapperInterface
{
    public function close(): void;
    public function isEOF():bool;
}
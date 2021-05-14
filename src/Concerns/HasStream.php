<?php


namespace JBernavaPrah\EloquentFS\Concerns;


use JBernavaPrah\EloquentFS\EloquentFSStreamWrapper;
use Illuminate\Support\Str;

trait HasStream
{

    private $stream;


    public static function bootHasStream()
    {
        if (!in_array(EloquentFSStreamWrapper::$streamWrapperProtocol, stream_get_wrappers())) {
            EloquentFSStreamWrapper::register();
        }

    }

    protected function createPathForFile(self $file): string
    {

        if (is_null($file->getKey())) {
            $file->{$file->getKeyName()} = Str::random(32);
        }

        return sprintf(
            '%s://%s',
            EloquentFSStreamWrapper::$streamWrapperProtocol,
            urlencode($file->getKey())
        );
    }

    public function stream($mode)
    {

        $path = $this->createPathForFile($this);
        $context = stream_context_create([
            EloquentFSStreamWrapper::$streamWrapperProtocol => [
                'file' => $this,
            ],
        ]);

        return fopen($path, $mode, false, $context);
    }


    protected function getStream()
    {
        if (!$this->stream) {
            $this->stream = $this->stream(self::$defaultOpenFileMode);
        }

        return $this->stream;
    }

    public function read(?int $length = null): string
    {

        $length = $length ?: self::$defaultChunkSize;

        return fread($this->getStream(), $length);
    }


    /**
     * @param $content
     * @return int
     */
    public function write($content): int
    {

        var_dump($this);
        die();

        return fwrite($this->getStream(), $content);
    }

    public function close(): bool
    {

        return fclose($this->getStream());
    }

}
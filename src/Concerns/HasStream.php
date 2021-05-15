<?php


namespace JBernavaPrah\EloquentFS\Concerns;


use Illuminate\Database\Eloquent\Model;
use JBernavaPrah\EloquentFS\EloquentFSStreamWrapper;
use Illuminate\Support\Str;
use JBernavaPrah\EloquentFS\Models\File;

trait HasStream
{

    protected $stream;

    public static function bootHasStream()
    {
        if (!in_array(EloquentFSStreamWrapper::$streamWrapperProtocol, stream_get_wrappers())) {
            EloquentFSStreamWrapper::register();
        }

    }

    public function initializeHasStream()
    {
        if (is_null($this->getKey())) {
            $this->{$this->getKeyName()} = Str::random(32);
        }

        $this->chunk_size = self::$defaultChunkSize;
    }

    protected function createPathForFile(): string
    {
        return sprintf(
            '%s://%s',
            EloquentFSStreamWrapper::$streamWrapperProtocol,
            urlencode($this->getKey())
        );
    }


    public function open($mode)
    {

        $path = $this->createPathForFile();
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
            $this->stream = $this->open(File::$defaultOpenFileMode);
        }

        return $this->stream;
    }

    public function read(?int $length = null): string
    {

        $length = $length ?: File::$defaultChunkSize;

        return fread($this->getStream(), $length);
    }


    /**
     * @param $content
     * @return int
     */
    public function write($content): int
    {

        return fwrite($this->getStream(), $content);
    }

    public function tell(): int
    {
        return ftell($this->getStream());
    }


    public function close(): bool
    {
        return fclose($this->getStream());
    }


}
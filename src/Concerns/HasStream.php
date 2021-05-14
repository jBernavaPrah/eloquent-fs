<?php


namespace FidesAds\GridFS\Concerns;


use FidesAds\GridFS\GridFSStreamWrapper;

trait HasStream
{

    private $stream;

    public static $defaultOpenFileMode = 'r+';

    public static $defaultChunkSize = 261120;

    public static function bootHasStream()
    {
        if (!in_array(GridFSStreamWrapper::$streamWrapperProtocol, stream_get_wrappers())) {
            GridFSStreamWrapper::register();
        }

    }

    private function createPathForFile(self $file): string
    {

        return sprintf(
            '%s://files/%s',
            GridFSStreamWrapper::$streamWrapperProtocol,
            urlencode($file->id ?? 'new')
        );
    }

    public function stream($mode)
    {

        $path = $this->createPathForFile($this);
        $context = stream_context_create([
            GridFSStreamWrapper::$streamWrapperProtocol => [
                'file' => $this,
            ],
        ]);

        return fopen($path, $mode, false, $context);
    }

    /**
     *
     * @param $content
     * @param string|null $filename
     * @param int|null $chunkSize
     * @return static
     */
    public static function createAndWrite($content, ?string $filename = null, ?int $chunkSize = null)
    {

        $file = new self();
        $file->chunk_size = $chunkSize ?: self::$defaultChunkSize;
        $file->filename = $filename;
        $file->save();

        $file->write($content);
        $file->close();

        return $file;

    }

    private function getCurrentStream()
    {
        if (!$this->stream) {
            $this->stream = $this->stream(self::$defaultOpenFileMode);
        }

        return $this->stream;
    }

    public function read(?int $length = null): string
    {

        $length = $length ?: self::$defaultChunkSize;

        return fread($this->getCurrentStream(), $length);
    }


    /**
     * @param $content
     * @return int
     */
    public function write($content): int
    {
        return fwrite($this->getCurrentStream(), $content);
    }

    public function close(): bool
    {
        return fclose($this->getCurrentStream());
    }

}
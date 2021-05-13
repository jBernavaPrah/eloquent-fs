<?php


namespace FidesAds\GridFS\Concerns;


use FidesAds\GridFS\GridFs;
use FidesAds\GridFS\GridFSStreamWrapper;

trait HasStream
{

    public static function bootHasStream()
    {
        if (!in_array(GridFs::$streamWrapperProtocol, stream_get_wrappers())) {
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

    public static function write(string $content, string $filename = null, int $chunkSize = null)
    {

        $file = new self();
        $file->chunk_size = $chunkSize ?: GridFs::$defaultChunkSize;
        $file->filename = $filename;
        $file->save();

        fwrite($file->stream('w+'), $content);

        return $file;

    }

}
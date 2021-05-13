<?php


namespace FidesAds\GridFS\Concerns;


use FidesAds\GridFS\GridFs;

trait HasWritableStream
{

    private string $buffer = '';
    private int $chuckOffset = 0;
    private int $length = 0;

    /**
     * Inserts binary data into GridFS via chunks.
     *
     * Data will be buffered internally until chunkSizeBytes are accumulated, at
     * which point a chunk document will be inserted and the buffer reset.
     *
     * @param string $data Binary data to write
     * @return integer
     */
    public function writeBytes($data)
    {


        if ($this->wasRecentlyCreated) {

            // TODO: Should this be an error condition? e.g. BadMethodCallException
            return;
        }

        $bytesRead = 0;

        while ($bytesRead != strlen($data)) {
            $initialBufferLength = strlen($this->buffer);
            $this->buffer .= substr($data, $bytesRead, GridFs::$defaultChunkSize - $initialBufferLength);
            $bytesRead += strlen($this->buffer) - $initialBufferLength;

            if (strlen($this->buffer) == GridFs::$defaultChunkSize) {
                $this->insertChunkFromBuffer();
            }
        }

        return $bytesRead;
    }
}

<?php


namespace JBernavaPrah\EloquentFS;


use JBernavaPrah\EloquentFS\Models\File;
use JBernavaPrah\EloquentFS\Models\FileChunk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stream wrapper for reading and writing a file on Eloquent.
 *
 * @internal
 */
class EloquentFSStreamWrapper
{
    /** @var resource|null Stream context (set by PHP) */
    public $context;

    /**
     * @var string
     */
    public static $streamWrapperProtocol = 'eloquent-stream';

    /** @var string */
    private $mode;

    private $triggerErrors = true;

    private $isClosed = false;

    /**
     * @var File
     */
    private $file;

    /**
     * @var int
     */
    private $pointer = 0;

    /**
     * @var Model|FileChunk|null
     */
    private $bufferedChunk = null;

    public function __destruct()
    {
        /* This destructor is a workaround for PHP trying to use the stream well
         * after all objects have been destructed. This can cause autoloading
         * issues and possibly segmentation faults during PHP shutdown. */
        $this->file = null;
        $this->bufferedChunk = null;
        $this->pointer = 0;
    }



    /**
     * Opens the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-open.php
     * @param string $path Path to the file resource
     * @param string $mode Mode used to open the file ( "x"/"x+", "c"/"c+" and 'e' not are supported yet)
     * @param integer $options Additional flags set by the streams API
     * @return boolean
     */
    public function stream_open(string $path, string $mode, $options): bool
    {
        $this->mode = $mode;

        $parts = explode('://', $path, 2);

        $protocol = $parts[0] ?: self::$streamWrapperProtocol;

        $context = stream_context_get_options($this->context);
        $this->file = $context[$protocol]['file'];
        $this->file->chunk_size = $this->file->chunk_size ?: File::$defaultChunkSize;

        $this->triggerErrors = $options === STREAM_REPORT_ERRORS;

        // 'r'	Open for reading only; place the file pointer at the beginning of the file.
        //'r+'	Open for reading and writing; place the file pointer at the beginning of the file.
        //'w'	Open for writing only; place the file pointer at the beginning of the file and truncate the file to zero length. If the file does not exist, attempt to create it.
        //'w+'	Open for reading and writing; place the file pointer at the beginning of the file and truncate the file to zero length. If the file does not exist, attempt to create it.
        //'a'	Open for writing only; place the file pointer at the end of the file. If the file does not exist, attempt to create it. In this mode, fseek() has no effect, writes are always appended.
        //'a+'	Open for reading and writing; place the file pointer at the end of the file. If the file does not exist, attempt to create it. In this mode, fseek() only affects the reading position, writes are always appended.

        $initializedFile = $this->initializeFile();

        if (!$initializedFile && $this->triggerErrors) {
            trigger_error('File not exists yet. First create it.', E_USER_WARNING);
        }
        return $initializedFile;
    }

    /**
     * Register the Eloquent stream wrapper.
     *
     */
    public static function register()
    {
        if (in_array(self::$streamWrapperProtocol, stream_get_wrappers())) {
            stream_wrapper_unregister(self::$streamWrapperProtocol);
        }

        stream_wrapper_register(self::$streamWrapperProtocol, static::class, STREAM_IS_URL);
    }

    /**
     * Closes the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-close.php
     */
    public function stream_close()
    {
        $this->isClosed = True;
    }

    /**
     * Returns whether the file pointer is at the end of the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-eof.php
     * @return boolean
     */
    public function stream_eof(): bool
    {

        return $this->pointer === $this->file->length;
    }

    public function stream_flush(): bool
    {

        if (!$this->bufferedChunk) {
            return true;
        }

        if (!$this->bufferedChunk->data) {
            $this->bufferedChunk = null;
            return true;
        }

        return $this->bufferedChunk->save();
    }

    /**
     * Write bytes to the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-write.php
     * @param string|null $data Data to write
     * @return integer The number of bytes written
     */
    public function stream_write(string $data)
    {

        if (!$this->isWriteMode() | $this->isClosed) {
            return false;
        }

        try {
            return $this->writeBytes($data);
        } catch (Throwable $e) {
            if ($this->triggerErrors) {
                trigger_error(sprintf('%s: %s', get_class($e), $e->getMessage()), E_USER_WARNING);
            }

            return false;
        }
    }


    /**
     * Read bytes from the stream.
     *
     * Note: this method may return a string smaller than the requested length
     * if data is not available to be read.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-read.php
     * @param integer $length Number of bytes to read
     * @return string|bool
     */
    public function stream_read(int $length)
    {

        if (!$this->isReadMode() | $this->isClosed) {
            return false;
        }

        try {
            return $this->readBytes($length);
        } catch (Throwable $e) {
            if ($this->triggerErrors) {
                trigger_error(sprintf('%s: %s', get_class($e), $e->getMessage()), E_USER_WARNING);
            }

            return false;
        }
    }

    /**
     * Seeks to specific location in a stream
     *
     * @see http://php.net/manual/en/streamwrapper.stream-seek.php
     * @param integer $offset Stream offset to seek to
     * @param integer $whence One of SEEK_SET, SEEK_CUR, or SEEK_END
     * @return boolean True if the position was updated and false otherwise
     */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {


        if (abs($offset) > $this->file->length) {
            return false;
        }

        // case of seek_set
        $newPosition = $offset;

        switch ($whence) {
            case SEEK_CUR:
                $newPosition = $this->pointer + $offset;
                break;
            case SEEK_END:
                $newPosition = $this->file->length + $offset;
                break;
        }


        if ($newPosition < 0 || $newPosition > $this->file->length) {
            return false;
        }

        $this->pointer = $newPosition;

        return true;
    }

    /**
     * Return information about the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-stat.php
     * @return array
     */
    public function stream_stat(): array
    {
        $stat = [
            0 => 0, 'dev' => 0,
            1 => 0, 'ino' => 0,
            2 => 0, 'mode' => 0,
            3 => 0, 'nlink' => 0,
            4 => 0, 'uid' => 0,
            5 => 0, 'gid' => 0,
            6 => -1, 'rdev' => -1,
            7 => 0, 'size' => 0,
            8 => 0, 'atime' => 0,
            9 => 0, 'mtime' => 0,
            10 => 0, 'ctime' => 0,
            11 => -1, 'blksize' => -1,
            12 => -1, 'blocks' => -1,
        ];


        $stat[2] = $stat['mode'] = $this->isReadMode() ? 0100444  // S_IFREG & S_IRUSR & S_IRGRP & S_IROTH
            : 0100222; // S_IFREG & S_IWUSR & S_IWGRP & S_IWOTH
        $stat[7] = $stat['size'] = $this->file->length;

        $stat[9] = $stat['mtime'] = $this->file->updated_at->timestamp ?? 0;
        $stat[10] = $stat['ctime'] = $this->file->created_at->timestamp ?? 0;

        $stat[11] = $stat['blksize'] = $this->file->chunk_size ?? -1;

        return $stat;
    }

    /**
     * Return the current position of the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-tell.php
     * @return integer The current position of the stream
     */
    public function stream_tell()
    {
        return $this->pointer;
    }


    private function setBufferedChunk($position): Model
    {
        return $this->bufferedChunk ?: $this->bufferedChunk = $this->file->chunks()->where('n', $position)->firstOrNew(['n' => $position, 'id' => Str::random(64)]);
    }

    private function writeBytes(string $data): int
    {

        // 'a'	Open for writing only; place the file pointer at the end of the file. If the file does not exist, attempt to create it. In this mode, fseek() has no effect, writes are always appended.
        //'a+'	Open for reading and writing; place the file pointer at the end of the file. If the file does not exist, attempt to create it. In this mode, fseek() only affects the reading position, writes are always appended.

        $pointer = $this->isAppendMode() ? $this->file->length + strlen($this->bufferedChunk->data ?? '') : $this->pointer;

        $startFromChunkNumber = (integer)ceil($pointer / $this->file->chunk_size);
        $positionOnChunkData = $startFromChunkNumber > 0 ? $pointer - (($startFromChunkNumber - 1) * $this->file->chunk_size) : 0;

        // increment startFromChunkNumber if the current chunk is already full
        if ($positionOnChunkData === $this->file->chunk_size) {
            $startFromChunkNumber += 1;
            $positionOnChunkData = 0;
        }

        $this->setBufferedChunk($startFromChunkNumber);
        $bytesRead = 0;

        while ($toWrite = substr($data, $bytesRead, $this->file->chunk_size - $positionOnChunkData)) {

            $this->bufferedChunk->data = substr_replace($this->bufferedChunk->data, $toWrite, $positionOnChunkData);

            if (strlen($this->bufferedChunk->data) === $this->file->chunk_size) {
                $this->bufferedChunk->save();
                $this->bufferedChunk = null;
                $this->setBufferedChunk(++$startFromChunkNumber);
            }

            $positionOnChunkData = 0;
            $bytesRead += strlen($toWrite);

            if (!$this->isAppendMode()) {
                $this->pointer += strlen($toWrite);
            }

        }

        // $this->file->getConnection()->commit();

        return $bytesRead;

    }

    private function readBytes(int $length): string
    {

        $startFromChunkNumber = (integer)floor($this->pointer / $this->file->chunk_size);
        $skipDataFromChunk = $this->pointer - ($startFromChunkNumber * $this->file->chunk_size);

        $chucks = $this->file->chunks()->where('n', '>=', $startFromChunkNumber)->orderBy('n')->lazy();

        $data = '';
        /** @var FileChunk $chuck */
        foreach ($chucks as $chuck) {

            $dataRead = substr($chuck->data, $skipDataFromChunk, $length - strlen($data));
            $data .= $dataRead;
            $this->pointer += strlen($dataRead);

            if (strlen($data) === $length) {
                break;
            }

        }
        return $data;

    }

    private function isAppendMode(): bool
    {
        return in_array($this->mode, ['a', 'a+']);
    }

    private function isWriteMode(): bool
    {
        return in_array($this->mode, ['w', 'r+', 'w+', 'a', 'a+']);
    }

    private function isReadMode(): bool
    {
        return in_array($this->mode, ['r', 'r+', 'w+', 'a+']);
    }

    private function initializeFile(): bool
    {

        if (in_array($this->mode, ['w', 'w+'])) {

            if ($created = $this->file->save()) {
                $this->file->chunks()->delete();
            }

            return $created;
        }

        if (in_array($this->mode, ['a', 'a+'])) {
            return $this->file->save();
        }

        return $this->file->exists;

    }


}
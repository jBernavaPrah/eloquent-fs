<?php


namespace JBernavaPrah\EloquentFS;


use Carbon\Carbon;
use Iterator;
use JBernavaPrah\EloquentFS\Exception\CorruptFileException;
use JBernavaPrah\EloquentFS\Exception\RuntimeException;
use JBernavaPrah\EloquentFS\Models\FsFile;
use JBernavaPrah\EloquentFS\Models\FsFileChunk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use function stream_wrapper_unregister;

/**
 * Stream wrapper for reading and writing a file on Eloquent.
 */
class EloquentFSStreamWrapper
{
    /** @var resource|null Stream context (set by PHP) */
    public $context;

    /**
     * @var string
     */
    public static $streamWrapperProtocol = 'efs';

    /** @var string */
    private string $mode;

    private bool $triggerErrors = true;


    /**
     * current file
     * @var FsFile|null
     */
    private ?FsFile $file;

    /**
     * Current pointer.
     * @var int
     */
    private int $pointer = 0;

    /**
     * Buffered chunk. Will be written on database when: Chunk data is === $defaultChunkSize or fflush() is called.
     * @var Model|FsFileChunk|null
     */
    private $bufferWrite = null;

    private int $fileSize;
    private int $fileChunkSize;


    public function __call($method, $params)
    {

        try {

            $method = Str::remove('stream_', $method);
            if (!method_exists($this, $method)) {
                return false;
            }

            return $this->{$method}(...$params);
        } catch (\Exception $e) {
            if ($this->triggerErrors) {
                trigger_error(sprintf('%s: %s', get_class($e), $e->getMessage()), E_USER_WARNING);
            }

            return false;
        }

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
    public function open(string $path, string $mode, int $options): bool
    {

        $this->triggerErrors = $options & STREAM_REPORT_ERRORS;

        $this->mode = $mode;
        $this->file = $this->findOrNewFile($path);

        if (!$this->file->chunk_size || $this->file->chunk_size < 0) {
            throw new CorruptFileException('file.chunk_size is not an integer >= 1');
        }

        if (Str::startsWith($mode, ['w', 'a'])) {
            // create file (if not already exists) for this modes..
            $this->file->save();
        }

        if (Str::startsWith($mode, ['w'])) {
            // delete all chunks, eventually, if mode are w/w+
            $this->file->chunks()->delete();
        }

        if (Str::startsWith($mode, 'r') && !$this->file->exists) {
            // for r mode, file need to exists on database..
            throw new RuntimeException('File not found.');
        }

        // use cached values to try to improve speed..
        $this->fileSize = $this->file->length;
        $this->fileChunkSize = $this->file->chunk_size;


        return true;
    }


    /**
     * Closes the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-close.php
     */
    public function close(): void
    {

    }

    /**
     * Delete a file
     * @param string $path
     *
     * @return bool
     * @see https://www.php.net/manual/en/streamwrapper.unlink.php
     */
    public function unlink(string $path): bool
    {
        return (new EloquentFS::$defaultFileClass)->where('id', $this->clearPath($path))->delete();
    }

    /**
     * Returns whether the file pointer is at the end of the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-eof.php
     * @return boolean
     */
    public function eof(): bool
    {
        return $this->pointer === ($this->fileSize + strlen($this->bufferWrite->data ?? ''));
    }

    public function flush(): bool
    {

        if (!$this->bufferWrite || !$this->bufferWrite->data) {
            $this->bufferWrite = null;
            return true;
        }

        return $this->bufferWrite->save();
    }

    /**
     * Write bytes to the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-write.php
     * @param string|null $data Data to write
     * @return integer The number of bytes written
     */
    public function write(string $data): int
    {

        if (!$this->isWriteMode()) {
            throw new RuntimeException('Bad file descriptor.');
        }

        // 'a'	Open for writing only; place the file pointer at the end of the file. If the file does not exist, attempt to create it. In this mode, fseek() has no effect, writes are always appended.
        //'a+'	Open for reading and writing; place the file pointer at the end of the file. If the file does not exist, attempt to create it. In this mode, fseek() only affects the reading position, writes are always appended.

        $pointer = $this->isAppendMode() ? $this->fileSize : $this->pointer;

        $chunkNumber = (integer)floor($pointer / $this->fileChunkSize);
        $positionOnChunkData = $pointer - ($chunkNumber * $this->fileChunkSize);

        $buffer = $this->getBufferWrite($chunkNumber);
        $bytesWritten = 0;

        while ($toWrite = substr($data, $bytesWritten, $this->fileChunkSize - $positionOnChunkData)) {

            $preReplaceLength = strlen($buffer->data);
            $buffer->data = substr_replace($buffer->data, $toWrite, $positionOnChunkData);
            $postReplaceLength = strlen($buffer->data);

            if ($this->fileChunkSize === $postReplaceLength) {
                $buffer->save();
                $buffer = $this->getBufferWrite(++$chunkNumber);
            }

            $positionOnChunkData = 0;
            $bytesWritten += strlen($toWrite);

            $this->fileSize += ($postReplaceLength - $preReplaceLength);
        }

        if (!$this->isAppendMode()) {
            $this->pointer += $bytesWritten;
        }


        return $bytesWritten;
    }

    protected function getBufferWrite($position): ?Model
    {
        if ($this->bufferWrite && $this->bufferWrite->n === $position) {
            return $this->bufferWrite;
        }

        $this->bufferWrite = $this->file
            ->chunks()
            ->firstOrNew(['n' => (integer)$position]);

        return $this->bufferWrite;

    }

    protected ?Iterator $iterator = null;
    protected ?string $currentChunkData = null;

    protected function getBufferRead(int $chunkNumber, $expectedChunkSize)
    {

        if (!$this->iterator) {
            $this->iterator = $this->file->chunks()->where('n', '>=', $chunkNumber)
                ->orderBy('n')->lazy(5)->getIterator();
        }

        if (!$this->iterator->valid()) {
            return false;
        }

        $chunk = $this->iterator->current();
        if ($chunk->n < $chunkNumber) {
            $this->iterator->next();
            $this->currentChunkData = null;
            return $this->getBufferRead($chunkNumber, $expectedChunkSize);
        }

        if ($chunk->n > $chunkNumber) {
            throw CorruptFileException::unexpectedIndex($chunk->n, $chunkNumber);
        }

        if ($this->currentChunkData) {
            return $this->currentChunkData;

        }

        $this->currentChunkData = $chunk->data;

        $actualChunkSize = strlen($this->currentChunkData);

        if ($actualChunkSize !== $expectedChunkSize) {
            throw CorruptFileException::unexpectedSize($actualChunkSize, $expectedChunkSize);
        }

        return $this->currentChunkData;
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
    public function read(int $length)
    {

        if (!$this->isReadMode()) {
            throw new Exception\RuntimeException('Bad file descriptor.');
        }

        $chunkNumber = (integer)floor($this->pointer / $this->fileChunkSize);
        $offset = $this->pointer - ($chunkNumber * $this->fileChunkSize);

        $numChunks = (integer)ceil($this->fileSize / $this->fileChunkSize);
        $expectedLastChunkSize = ($this->fileSize - (($numChunks - 1) * $this->fileChunkSize));


        $data = '';

        while (true) {

            $bufferData = $this->getBufferRead($chunkNumber, $chunkNumber === $numChunks - 1
                ? $expectedLastChunkSize
                : $this->fileChunkSize);

            if (!$bufferData) {
                break;
            }

            $dataRead = substr($bufferData, $offset, $length - strlen($data));
            $data .= $dataRead;

            if (strlen($data) === $length) {
                break;
            }

            $offset = 0;
            ++$chunkNumber;

        }

        $this->pointer += strlen($data);

        return $data;
    }

    /**
     * Seeks to specific location in a stream
     *
     * @see http://php.net/manual/en/streamwrapper.stream-seek.php
     * @param integer $offset Stream offset to seek to
     * @param integer $whence One of SEEK_SET, SEEK_CUR, or SEEK_END
     * @return bool True if the position was updated and false otherwise
     */
    public function seek(int $offset, int $whence = SEEK_SET): bool
    {

        if (abs($offset) > $this->fileSize) {
            return false;
        }

        // case of seek_set
        $newPosition = $offset;

        switch ($whence) {
            case SEEK_CUR:
                $newPosition = $this->pointer + $offset;
                break;
            case SEEK_END:
                $newPosition = $this->fileSize + $offset;
                break;
        }


        if ($newPosition < 0 || $newPosition > $this->fileSize) {
            return false;
        }

        $this->pointer = $newPosition;

        return true;
    }

    /**
     * Retrieve information about a file
     * @param string $path
     * @param int $flags
     * @return false|int[]
     */
    public function url_stat(string $path, int $flags)
    {

        $file = $this->findOrNewFile($path);
        if (!$file) {

            if (!($flags & STREAM_URL_STAT_QUIET)) {
                trigger_error(sprintf('Impossible to get statistics. File %s not found.', $path), E_USER_WARNING);
            }

            return false;
        }


        return $this->getStatistics($file);

    }

    protected function getStatistics(FsFile $file): array
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


        $stat[2] = $stat['mode'] = "0100777";
        $stat[7] = $stat['size'] = $file->length;

        $stat[9] = $stat['mtime'] = $file->updated_at->timestamp ?? 0;
        $stat[10] = $stat['ctime'] = $file->created_at->timestamp ?? 0;

        $stat[11] = $stat['blksize'] = (int)$file->chunk_size ?? -1;

        return $stat;
    }

    /**
     * Return information about the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-stat.php
     * @return array
     */
    public function stat(): array
    {
        return $this->getStatistics($this->file);
    }

    /**
     * Return the current position of the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-tell.php
     * @return integer The current position of the stream
     */
    public function tell(): int
    {
        return $this->pointer;
    }


    /**
     * @param string $path
     * @param int $option
     * @param mixed $value
     * @return bool
     */
    public function metadata(string $path, int $option, $value): bool
    {

        if ($option & STREAM_META_TOUCH) {
            $file = $this->findOrNewFile($path);
            $file->updated_at = $value[0] ?? Carbon::now();
            $file->save();
            return true;
        }

        return false;

    }


    protected function clearPath($path): string
    {
        return Str::remove(self::$streamWrapperProtocol . "://", $path);
    }

    protected function findOrNewFile($path): FsFile
    {

        // get id from path
        $id = $this->clearPath($path);

        if ($this->context) {
            $context = stream_context_get_options($this->context);
            $file = $context[self::$streamWrapperProtocol]['file'] ?? null;
            if ($file) {
                $file->id = $id ?: $file->id;
                $file->chunk_size = $file->chunk_size ?: EloquentFS::$defaultChunkSize;
                return $file;
            }
        }


        /** @var FsFile $class */
        $class = EloquentFS::$defaultFileClass;
        $file = (new $class)::findOrNew($id);
        $file->id = $id ?: $file->id;
        $file->chunk_size = $file->chunk_size ?: EloquentFS::$defaultChunkSize;
        return $file;

    }

    /**
     * Register the Eloquent stream wrapper.
     *
     */
    public static function register()
    {

        if (in_array(self::$streamWrapperProtocol, stream_get_wrappers())) {
            self::unregister();
        }

        stream_wrapper_register(self::$streamWrapperProtocol, static::class, STREAM_IS_URL);

    }

    public static function unregister()
    {
        stream_wrapper_unregister(self::$streamWrapperProtocol);
    }

    protected function isAppendMode(): bool
    {
        return Str::startsWith($this->mode, 'a');
    }

    protected function isWriteMode(): bool
    {
        return Str::startsWith($this->mode, ['w', 'r+', 'a']);
    }

    protected function isReadMode(): bool
    {
        return Str::startsWith($this->mode, ['w+', 'r', 'a+']);
    }


}
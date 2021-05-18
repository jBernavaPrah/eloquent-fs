<?php


namespace JBernavaPrah\EloquentFS;


use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use JBernavaPrah\EloquentFS\Exception\CorruptFileException;
use JBernavaPrah\EloquentFS\Models\File;
use JBernavaPrah\EloquentFS\Models\FileChunk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;
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
    private $mode;

    private $triggerErrors = true;

    /**
     * File Model to use to save on database.
     * @var string
     */
    public static $defaultFileClass = File::class;

    /**
     * Default chunk to save in database.
     * @var int
     */
    public static $defaultChunkSize = 261120;

    /**
     * current file
     * @var File
     */
    private $file;

    /**
     * Current pointer.
     * @var int
     */
    private $pointer = 0;

    /**
     * Buffered chunk. Will be written on database when: Chunk data is === $defaultChunkSize or fflush() is called.
     * @var Model|FileChunk|null
     */
    private $bufferedChunk = null;

    /**
     * Register the Eloquent stream wrapper.
     *
     */
    public static function register(?string $connection = null)
    {

        if (in_array(self::$streamWrapperProtocol, stream_get_wrappers())) {
            self::unregister();
        }

        stream_wrapper_register(self::$streamWrapperProtocol, static::class, STREAM_IS_URL);

        // todo: test if this work also with extends Models
        File::resolveConnection($connection);
        FileChunk::resolveConnection($connection);

    }

    public static function unregister()
    {
        stream_wrapper_unregister(self::$streamWrapperProtocol);
    }


    /**
     * @param Manager $manager
     * @param string $connection
     * @param array $paths
     * @throws BindingResolutionException
     */
    public static function migrate(Manager $manager, string $connection = 'default', array $paths = [__DIR__ . '/../database/migrations'])
    {

        $currentConnection = $manager->getDatabaseManager()->getDefaultConnection();
        $manager->getDatabaseManager()->setDefaultConnection($connection);

        $container = Container::getInstance();
        $databaseMigrationRepository = new DatabaseMigrationRepository($manager->getDatabaseManager(), 'migrations');
        if (!$databaseMigrationRepository->repositoryExists()) {
            $databaseMigrationRepository->createRepository();
        }

        $container->instance(MigrationRepositoryInterface::class, $databaseMigrationRepository);
        $container->instance(ConnectionResolverInterface::class, $manager->getDatabaseManager());

        /** @var Migrator $migrator */
        $migrator = $container->make(Migrator::class);
        $migrator->run($paths);

        $manager->getDatabaseManager()->setDefaultConnection($currentConnection);

    }


    public function __destruct()
    {
        /* This destructor is a workaround for PHP trying to use the stream well
         * after all objects have been destructed. This can cause autoloading
         * issues and possibly segmentation faults during PHP shutdown. */
        $this->file = null;
        $this->bufferedChunk = null;
        $this->pointer = 0;
    }

    protected function clearPath($path): string
    {
        return Str::remove(self::$streamWrapperProtocol . "://", $path);
    }

    protected function findOrNewFile($path): File
    {

        // get id from path
        $id = $this->clearPath($path) ?: Str::random(32);

        if ($this->context) {
            $context = stream_context_get_options($this->context);
            $file = $context[self::$streamWrapperProtocol]['file'] ?? null;
            if ($file) {
                $file->id = $file->id ?: $id;
                $file->chunk_size = $file->chunk_size ?: self::$defaultChunkSize;
                return $file;
            }
        }


        /** @var File $class */
        $class = self::$defaultFileClass;
        $file = (new $class)::findOrNew($id);
        $file->id = $file->id ?: $id;
        $file->chunk_size = $file->chunk_size ?: self::$defaultChunkSize;
        return $file;

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
    public function stream_open(string $path, string $mode, int $options): bool
    {
        $this->mode = $mode;

        $this->file = $this->findOrNewFile($path);

        if (!$this->file->id) {
            throw new CorruptFileException('file.id is not an string');
        }

        if (!$this->file->chunk_size || $this->file->chunk_size < 0) {
            throw new CorruptFileException('file.chunk_size is not an integer >= 1');
        }

        $this->triggerErrors = $options === STREAM_REPORT_ERRORS;

        if (Str::startsWith($this->mode, 'r') && !$this->file->exists) {

            trigger_error(sprintf('File %s not found.', $path), E_USER_WARNING);
            // file need to exists on r/r+ mode!
            return false;
        }

        // create new file if not exists now..
        if (!$this->file->save() && $this->triggerErrors) {
            trigger_error('Impossible to save file on database.', E_USER_WARNING);
            return false;
        }

        if (Str::startsWith($this->mode, 'w')) {
            // delete all chunks if mode are w/ w+
            $this->file->chunks()->delete();
        }

        return true;
    }


    /**
     * Closes the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-close.php
     */
    public function stream_close()
    {
        // nothing to do here..
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

        return File::where('id', $this->clearPath($path))->delete();
    }

    /**
     * Returns whether the file pointer is at the end of the stream.
     *
     * @see http://php.net/manual/en/streamwrapper.stream-eof.php
     * @return boolean
     */
    public function stream_eof(): bool
    {

        return $this->pointer === ($this->file->length + strlen($this->bufferedChunk->data ?? ''));
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

        if (!$this->isWriteMode()) {
            trigger_error('Bad file descriptor.', E_USER_WARNING);
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

        if (!$this->isReadMode()) {

            trigger_error('Bad file descriptor.', E_USER_WARNING);
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
     * @return bool True if the position was updated and false otherwise
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
     * Retrieve information about a file
     * @param string $path
     * @param int $flags
     * @return false|int[]
     */
    public function url_stat(string $path, int $flags)
    {

        $id = $this->clearPath($path);
        $file = File::find($id);
        if (!$file) {

            if (!($flags & STREAM_URL_STAT_QUIET)) {
                trigger_error(sprintf('Impossible to get statistics. File %s not found.', $id), E_USER_WARNING);
            }

            return false;
        }


        return $this->getStatistics($file);

    }

    protected function getStatistics(File $file): array
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
    public function stream_stat(): array
    {
        return $this->getStatistics($this->file);
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


    /**
     * @param string $path
     * @param int $option
     * @param mixed $value
     * @return bool
     */
    public function stream_metadata(string $path, int $option, $value): bool
    {

        if ($option & STREAM_META_TOUCH) {
            $file = $this->findOrNewFile($path);
            $file->updated_at = $value[0] ?? Carbon::now();
            $file->save();
            return true;
        }

        return false;

    }


    private function setBufferedChunk($position): Model
    {

        return $this->bufferedChunk ?: $this->bufferedChunk = $this->file
            ->chunks()
            ->firstOrNew(['n' => (integer)$position], ['id' => Str::random(64)]);
    }

    private function writeBytes(string $data): int
    {

        // 'a'	Open for writing only; place the file pointer at the end of the file. If the file does not exist, attempt to create it. In this mode, fseek() has no effect, writes are always appended.
        //'a+'	Open for reading and writing; place the file pointer at the end of the file. If the file does not exist, attempt to create it. In this mode, fseek() only affects the reading position, writes are always appended.

        $pointer = $this->isAppendMode() ? $this->file->length + strlen($this->bufferedChunk->data ?? '') : $this->pointer;

        $startFromChunkNumber = (integer)floor($pointer / $this->file->chunk_size);
        $positionOnChunkData = $pointer - ($startFromChunkNumber * $this->file->chunk_size);

        $this->setBufferedChunk($startFromChunkNumber);
        $bytesWritten = 0;

        while ($toWrite = substr($data, $bytesWritten, $this->file->chunk_size - $positionOnChunkData)) {

            $this->bufferedChunk->data = substr_replace($this->bufferedChunk->data, $toWrite, $positionOnChunkData);

            if (strlen($this->bufferedChunk->data) === $this->file->chunk_size) {
                $this->bufferedChunk->save();
                $this->bufferedChunk = null;
                $this->setBufferedChunk(++$startFromChunkNumber);
            }

            $positionOnChunkData = 0;
            $bytesWritten += strlen($toWrite);

            if (!$this->isAppendMode()) {
                $this->pointer += strlen($toWrite);
            }

        }

        // $this->file->getConnection()->commit();

        return $bytesWritten;

    }

    private function readBytes(int $length): string
    {

        $index = (integer)floor($this->pointer / $this->file->chunk_size);
        $offset = $this->pointer - ($index * $this->file->chunk_size);

        $numChunks = (integer)ceil($this->file->length / $this->file->chunk_size);
        $expectedLastChunkSize = ($this->file->length - (($numChunks - 1) * $this->file->chunk_size));

        $chunks = $this->file->chunks()->where('n', '>=', $index)
            ->orderBy('n')
            ->lazy();

        $data = '';
        /** @var FileChunk $chuck */
        foreach ($chunks as $chuck) {

            if ($index != $chuck->n) {
                throw CorruptFileException::unexpectedIndex($chuck->n, $index);
            }

            $actualChunkSize = strlen($chuck->data);

            $expectedChunkSize = $index === $numChunks - 1
                ? $expectedLastChunkSize
                : $this->file->chunk_size;

            if ($actualChunkSize !== $expectedChunkSize) {
                throw CorruptFileException::unexpectedSize($actualChunkSize, $expectedChunkSize);
            }

            $dataRead = substr($chuck->data, $offset, $length - strlen($data));

            $offset = 0;

            ++$index;

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
        return Str::startsWith($this->mode, 'a');
    }

    private function isWriteMode(): bool
    {
        return Str::startsWith($this->mode, ['w', 'r+', 'a']);
    }

    private function isReadMode(): bool
    {
        return Str::startsWith($this->mode, ['w+', 'r', 'a+']);
    }


}
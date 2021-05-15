<?php


namespace JBernavaPrah\EloquentFS\Concerns;


use Illuminate\Database\Eloquent\Model;
use JBernavaPrah\EloquentFS\EloquentFSStreamWrapper;
use Illuminate\Support\Str;
use JBernavaPrah\EloquentFS\Exception\RuntimeException;
use JBernavaPrah\EloquentFS\Models\File;
use function Webmozart\Assert\Tests\StaticAnalysis\string;


/**
 * Trait HasStream
 * @package JBernavaPrah\EloquentFS\Concerns
 * @method int write(mixed $data)
 * @method int seek(int $position, int $whence = SEEK_SET)
 * @method int tell()
 * @method bool flush()
 */
trait HasStream
{

    protected $stream;

    protected static $allowedModes = ['a', 'a+', 'r', 'r+', 'w', 'w+'];

    public static $defaultChunkSize = 261120;

    public static $defaultOpenFileMode = 'a+';

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


    /**
     * Open new stream for this file.
     * @param $mode - Valid Values are: r, r+, w, w+, a, a+
     * @return false|resource
     */
    public function open($mode)
    {

        if (!in_array($mode, self::$allowedModes)) {
            throw new RuntimeException("Mode $mode not allowed");
        }

        $path = $this->createPathForFile();
        $context = stream_context_create([
            EloquentFSStreamWrapper::$streamWrapperProtocol => [
                'file' => $this,
            ],
        ]);

        return fopen($path, $mode, false, $context);
    }


    /**
     * Close stream for this file.
     * @return bool
     */
    public function close(): bool
    {

        $closed = fclose($this->getStream());
        $this->stream = null;
        return $closed;

    }

    public function read(int $length = null): string
    {

        $length = $length ?: self::$defaultChunkSize;

        $contents = '';
        while (!feof($this->getStream())) {
            $contents .= fread($this->getStream(), $length - strlen($contents));
        }
        return $contents;
    }


    protected function getStream()
    {
        if (!$this->stream) {
            $this->stream = $this->open(self::$defaultOpenFileMode);
        }

        return $this->stream;
    }

    public function __call($method, $parameters)
    {

        $implementedFileFunction = ['read', 'write', 'seek', 'tell', 'flush'];

        if (in_array("$method", $implementedFileFunction) && function_exists("f$method")) {
            array_unshift($parameters, $this->getStream());
            return call_user_func("f$method", ...$parameters);
        }

        return parent::__call($method, $parameters);
    }


}
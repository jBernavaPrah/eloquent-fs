<?php


namespace FidesAds\GridFS;


use FidesAds\GridFS\Models\File;
use FidesAds\GridFS\Models\FileChunk;

class GridFs
{

    /**
     * The GridFS library version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * The file model class name.
     *
     * @var string
     */
    public static string $fileModel = File::class;

    /**
     * The file chuck model class name.
     *
     * @var string
     */
    public static string $fileChunkModel = FileChunk::class;

    /**
     * The chunk size in bytes. Defaults to 261120 (i.e. 255 KiB).
     *
     * @var int
     */
    public static int $defaultChunkSize = 261120;

    /**
     * Indicates if Cashier migrations will be run.
     *
     * @var bool
     */
    public static bool $runsMigrations = true;


    public static string $streamWrapperProtocol = 'eloquent-gridfs';

    /**
     * Configure GridFS to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations(): GridFs
    {
        static::$runsMigrations = false;

        return new static;
    }

    /**
     * Set the file model class name.
     *
     * @param string $fileModel
     * @return void
     */
    public static function useFileModel(string $fileModel)
    {
        static::$fileModel = $fileModel;
    }

    /**
     * Set the file chuck model class name.
     *
     * @param string $fileChunkModel
     * @return void
     */
    public static function useFileChunkModel(string $fileChunkModel)
    {
        static::$fileChunkModel = $fileChunkModel;
    }


}

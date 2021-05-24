<?php

namespace JBernavaPrah\EloquentFS\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use JBernavaPrah\EloquentFS\EloquentFS;
use JBernavaPrah\EloquentFS\Models\FsFile;

/**
 * Class FsFileFactory
 * @package Database\Factories
 *
 */
class FsFileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FsFile::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'chunk_size' => EloquentFS::$defaultChunkSize,
        ];
    }

}

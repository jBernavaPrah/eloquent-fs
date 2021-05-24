<?php

namespace JBernavaPrah\EloquentFS\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use JBernavaPrah\EloquentFS\Database\Factories\FsFileFactory;
use JBernavaPrah\EloquentFS\EloquentFSStreamWrapper;
use JBernavaPrah\EloquentFS\Traits\HasDynamicConnection;
use JBernavaPrah\EloquentFS\Traits\HasUuid;

/**
 * Class File
 * @package JBernavaPrah\EloquentFS\Models
 *
 * @property string $id
 * @property-read  integer $length
 * @property  integer $chunk_size
 * @property mixed|null $metadata
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 *
 */
class FsFile extends Model
{

    use HasDynamicConnection;
    use HasUuid;
    use HasFactory;

    protected static function newFactory()
    {
        return new FsFileFactory();
    }

    public static $defaultModelFileChunk = FsFileChunk::class;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'json',
        'chunk_size' => 'integer',
    ];

    protected function getLengthAttribute()
    {

        /** @var FsFileChunk $lastChunk */
        $lastChunk = $this->chunks()->latest('n')->first();
        if (!$lastChunk) {
            return 0;
        }

        return ($this->chunk_size * $lastChunk->n) + strlen($lastChunk->data);

    }

    /**
     * todo: Implement filter by length
     * @param Builder $builder
     * @return Builder
     */
    protected function scopeWhereLength(Builder $builder): Builder
    {

        return $builder;

    }

    /**
     * @return HasMany
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(self::$defaultModelFileChunk);
    }

    protected function context()
    {
        return stream_context_create([
            EloquentFSStreamWrapper::$streamWrapperProtocol => [
                'file' => $this,
            ],
        ]);
    }


    protected function getPathFile(): string
    {
        return EloquentFSStreamWrapper::$streamWrapperProtocol . "://$this->id";
    }

    /**
     * Open new stream for this file.
     * @param $mode - Valid Values are: r, r+, w, w+, a, a+
     * @return false|resource
     */
    public function stream($mode)
    {
        return fopen($this->getPathFile(), $mode, false, $this->context());
    }

    public function read(int $offset = 0, int $length = null): string
    {
        return file_get_contents($this->getPathFile(), false, $this->context(), ...func_get_args());
    }

    /**
     * @param File|UploadedFile|string $data - Can be string File or UploadFile
     * @param false $append
     * @return false|int
     */
    public function write($data, $append = false)
    {

        if ($data instanceof File | $data instanceof UploadedFile) {
            $data = fopen($data->path(), 'r');
        }

        return file_put_contents($this->getPathFile(), $data, $append ? FILE_APPEND : 0, $this->context());
    }

    /**
     * @param File|UploadedFile|string $data - Can be string, File or UploadFile
     * @return FsFile|bool
     */
    public static function put($data)
    {
        $file = new self;
        if ($file->write($data) === false) {
            return false;
        }
        return $file;
    }


}

<?php

namespace JBernavaPrah\EloquentFS\Models;


use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
class File extends Model
{

    public $incrementing = false;

    public static $defaultModelFileChunk = FileChunk::class;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'json',
        'chunk_size' => 'integer'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function (File $file) {
            $file->id = $file->id ?: Str::random(32);
        });
    }


    public function getLengthAttribute()
    {

        /** @var FileChunk $lastChunk */
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

    /**
     * Open new stream for this file.
     * @param $mode - Valid Values are: r, r+, w, w+, a, a+
     * @return false|resource
     */
    public function open($mode)
    {
        return fopen("efs://$this->id", $mode, false);
    }

    public function read(int $length = null): string
    {
        return file_get_contents("efs://$this->id", false, null, 0, $length);
    }

    public function write($data)
    {
        return file_put_contents("efs://$this->id", $data);
    }


}

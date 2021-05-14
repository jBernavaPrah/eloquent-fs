<?php

namespace FidesAds\GridFS\Models;


use Carbon\Carbon;
use FidesAds\GridFS\Concerns\HasStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class File
 * @package FidesAds\GridFS\Models
 *
 * @property string $id
 * @property string|null $filename
 * @property-read  integer $length
 * @property integer $chunk_size
 * @property mixed|null $metadata
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 */
class File extends Model
{

    use SoftDeletes;
    use HasStream;

    protected $guarded = [];


    protected $casts = [
        'metadata' => 'json'
    ];

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
        return $this->hasMany(FileChunk::class);
    }


}

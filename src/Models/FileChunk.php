<?php

namespace FidesAds\GridFS\Models;

use FidesAds\GridFS\GridFs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class FileChuck
 * @package FidesAds\GridFS\Models
 *
 * @property string $id
 * @property-read File $file
 * @property string $file_id
 * @property number $n
 * @property mixed $data
 *
 * @method self|Builder whereN(int $n)
 */
class FileChunk extends Model
{

    public $timestamps = false;

    protected $guarded = [];

    public function file(): BelongsTo
    {
        return $this->belongsTo(GridFs::$fileModel);
    }


}

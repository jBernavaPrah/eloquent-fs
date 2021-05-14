<?php

namespace JBernavaPrah\EloquentFS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class FileChuck
 * @package JBernavaPrah\EloquentFS\Models
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

    static $defaultFileModel = File::class;

    public function file(): BelongsTo
    {
        return $this->belongsTo(self::$defaultFileModel);
    }


}

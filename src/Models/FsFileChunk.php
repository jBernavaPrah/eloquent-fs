<?php

namespace JBernavaPrah\EloquentFS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JBernavaPrah\EloquentBinaryCast\Casts\BinaryCast;
use JBernavaPrah\EloquentFS\Traits\HasDynamicConnection;
use JBernavaPrah\EloquentFS\Traits\HasUuid;

/**
 * Class FileChuck
 * @package JBernavaPrah\EloquentFS\Models
 *
 * @property string $id
 * @property-read FsFile $file
 * @property string $file_id
 * @property number $n
 * @property mixed $data
 *
 * @method self|Builder whereN(int $n)
 */
class FsFileChunk extends Model
{

    use HasDynamicConnection;
    use HasUuid;

    public $timestamps = false;

    protected $guarded = [];

    static string $defaultFileModel = FsFile::class;

    protected $casts = [
        'n' => 'integer',
        'data' => BinaryCast::class
    ];


    public function file(): BelongsTo
    {
        return $this->belongsTo(self::$defaultFileModel);
    }


}

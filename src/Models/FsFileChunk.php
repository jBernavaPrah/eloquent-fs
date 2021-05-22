<?php

namespace JBernavaPrah\EloquentFS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JBernavaPrah\EloquentBinaryCast\BinaryCast;
use JBernavaPrah\EloquentFS\Traits\HasDynamicConnection;

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

    public $timestamps = false;
    public $incrementing = false;

    protected $guarded = [];

    static string $defaultFileModel = FsFile::class;

    protected $casts = [
        'id' => BinaryCast::class,
        'fs_file_id' => BinaryCast::class,
        'n' => 'integer',
        'data' => BinaryCast::class
    ];


    public function file(): BelongsTo
    {
        return $this->belongsTo(self::$defaultFileModel);
    }


}

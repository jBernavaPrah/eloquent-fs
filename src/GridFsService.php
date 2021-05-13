<?php


namespace FidesAds\GridFS;


use FidesAds\GridFS\Models\File;
use FidesAds\GridFS\Models\FileChunk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class GridFsService
{

    /**
     * @var mixed
     */
    private Model $file;

    public function __construct()
    {
        $this->file = new GridFs::$fileModel;
    }

    public function openUploadStream()
    {
        // return file stream
    }

    public function content(File $file)
    {

    }


}

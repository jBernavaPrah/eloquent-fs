<?php


namespace Tests;


use JBernavaPrah\EloquentFS\Models\File;


class ModelTest extends TestCase
{


    function testCreateFile()
    {

        $content = str_pad('X', 12, 'X');

        File::write($content);


        $this->assertEquals(4, $file->chunks()->count());
        foreach ($file->chunks() as $chunk) {
            $this->assertEquals('XXX', $chunk->data);
        }

    }

    function testCreateWithLessThatChunk()
    {

        $file = File::createAndWrite('XX', 'filename.txt', 3);
        $this->assertEquals(1, $file->chunks()->count());
        $this->assertEquals('XX', $file->chunks()->first()->data);

    }

    function testCreateFileWithDefaults()
    {
        $file = File::createAndWrite('XX');
        $this->assertNull($file->filename);
        $this->assertEquals(File::$defaultChunkSize, $file->chunk_size);
        $this->assertEquals(2, $file->length);
    }


}
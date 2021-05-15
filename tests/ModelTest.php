<?php


namespace Tests;


use JBernavaPrah\EloquentFS\Exception\RuntimeException;
use JBernavaPrah\EloquentFS\Models\File;


class ModelTest extends TestCase
{


    function testFile()
    {

        $content = 'ABCDEFGHILMNOPQRSTUVWXYZ';

        $file = new File();
        $file->chunk_size = 3;
        $this->assertEquals(strlen($content), $file->write($content));
        $file->flush();
        $this->assertEquals($content, $file->read(160000));

        $this->assertEquals(0, $file->seek(0));
        $this->assertEquals(substr($content, 0, 4), $file->read(4));

        $this->assertEquals(0, $file->seek(-4, SEEK_END));
        $this->assertEquals(strlen($content) - 4, $file->tell());
        $this->assertEquals(substr($content, -4, 4), $file->read(4));


        // reset
        $file->close();
        $file->open('w+');
        $this->assertEquals(0, $file->tell());


    }


    function testCreateWithLessThatChunk()
    {

        $file = new File();

        $file->write('XX');

        // only one chuck is really written..
        $this->assertEquals('', $file->read(8200));
        $file->close();

        $this->assertEquals(1, $file->chunks()->count());
        $this->assertEquals('XX', $file->chunks()->first()->data);

    }

    function testCreateFileWithDefaults()
    {
        $file = new File();
        $file->write('XX');
        $file->close();
        $this->assertIsString($file->id);
        $this->assertEquals(File::$defaultChunkSize, $file->chunk_size);
        $this->assertEquals(2, $file->length);
    }


}
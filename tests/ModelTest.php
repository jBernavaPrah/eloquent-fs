<?php


namespace Tests;


use JBernavaPrah\EloquentFS\Exception\RuntimeException;
use JBernavaPrah\EloquentFS\Models\File;


class ModelTest extends TestCase
{


    function testCreateFile()
    {

        $content = str_pad('X', 12, 'X');

        $file = new File();
        $file->chunk_size = 3;
        $file->write($content);
        $file->close();

        $this->assertEquals(4, $file->chunks()->count());
        foreach ($file->chunks() as $chunk) {
            $this->assertEquals('XXX', $chunk->data);
        }

    }

    function testCreateStreamFromStatic(){

        File::open('a+');

        File::write('something');

        $this->expectNotToPerformAssertions();

    }

    function testThrowExceptionIfTryToChangeChunkSizeAfterWritten()
    {

        $this->expectException(RuntimeException::class);
        $file = new File();
        //$file->chunk_size = 4;
        $file->write('aaa');
        $file->chunk_size = 6;
        //$file->close();

    }

    function testCreateWithLessThatChunk()
    {

        $file = new File();

        $file->write('XX');

        // only one chuck is really written..
        $this->assertEquals('', $file->read());
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
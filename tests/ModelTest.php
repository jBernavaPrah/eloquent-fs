<?php


namespace JBernavaPrah\EloquentFS\Tests;


use JBernavaPrah\EloquentFS\Models\File;


class ModelTest extends TestCase
{


    function testFileModel()
    {

        $content = 'foobar';

        $file = new File();
        $this->assertEquals(6, $file->write($content));

        $this->assertEquals('foobar', $file->read());
        $this->assertEquals('fo', $file->read(0, 2));

        $this->assertIsResource($file->stream('w'));

        $this->assertEquals(6, $file->write($content, false));
        $this->assertEquals('foobar', $file->read());

        $this->assertEquals(6, $file->write($content));
        $this->assertEquals('foobarfoobar', $file->read());


    }


}
<?php


namespace JBernavaPrah\EloquentFS\Tests;


use Illuminate\Database\Eloquent\Model;
use JBernavaPrah\EloquentFS\EloquentFS;
use JBernavaPrah\EloquentFS\Models\FsFile;


class ModelTest extends TestCase
{


    function testFileModel()
    {

        $content = 'foobar';

        $file = new FsFile();
        $this->assertEquals(6, $file->write($content));

        $this->assertEquals('foobar', $file->read());
        $this->assertEquals('fo', $file->read(0, 2));

        $this->assertIsResource($file->stream('w'));

        $this->assertEquals(6, $file->write($content, false));
        $this->assertEquals('foobar', $file->read());

        $this->assertEquals(6, $file->write($content));
        $this->assertEquals('foobarfoobar', $file->read());


    }

    function testFileModelChangeConnection()
    {

        EloquentFS::$connection = 'abc';

        $this->expectException(\InvalidArgumentException::class);
        $file = new FsFile();
        $file->getConnection();


    }


}
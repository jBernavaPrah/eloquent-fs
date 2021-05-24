<?php


namespace JBernavaPrah\EloquentFS\Tests;


use Illuminate\Http\UploadedFile;
use JBernavaPrah\EloquentFS\EloquentFS;
use JBernavaPrah\EloquentFS\Models\FsFile;


class ModelTest extends TestCase
{

    protected function tearDown(): void
    {
        parent::tearDown(); // TODO: Change the autogenerated stub

        EloquentFS::$connection = null;
    }

    function testFileModel()
    {

        $content = 'foobar';

        $file = new FsFile();
        $this->assertEquals(6, $file->write($content));

        $this->assertEquals('foobar', $file->read());
        $this->assertEquals('fo', $file->read(0, 2));

        $this->assertIsResource($file->stream('w'));

        $this->assertEquals(6, $file->write($content));
        $this->assertEquals(6, $file->write($content, true));
        $this->assertEquals('foobarfoobar', $file->read());


        $this->assertEquals(6, $file->write($content));
        $this->assertEquals('foobar', $file->read());


    }

    function testFileModelChangeConnection()
    {

        EloquentFS::$connection = 'abc';

        $this->expectException(\InvalidArgumentException::class);
        $file = new FsFile();
        $file->getConnection();

        EloquentFS::$connection = null;


    }

    function testUploadedFileFromFake()
    {

        $file = UploadedFile::fake()
            ->image('avatar.jpg', 300, 400)
            ->size(261100);

        $fsFile = new FsFile();
        $fsFile->write($file);

        $this->assertEquals($file->get(), $fsFile->read());
        $this->assertNotEmpty($fsFile->id);

    }

}
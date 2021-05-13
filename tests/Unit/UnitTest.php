<?php


namespace FidesAds\GridFS\Tests\Unit;


use FidesAds\GridFS\GridFs;
use FidesAds\GridFS\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use phpDocumentor\Reflection\Assets\CustomParam;

class UnitTest extends \FidesAds\GridFS\Tests\TestCase
{

    use DatabaseMigrations;

    function testCreateFile()
    {

        $content = str_pad('X', 12, 'X');

        $file = File::write($content, 'filename.txt', 3);
        $this->assertEquals(4, $file->chunks()->count());
        foreach ($file->chunks() as $chunk) {
            $this->assertEquals('XXX', $chunk->data);
        }

    }

    function testCreateWithLessThatChunk()
    {


        $file = File::write('XX', 'filename.txt', 3);
        $this->assertEquals(1, $file->chunks()->count());
        $this->assertEquals('XX', $file->chunks()->first()->data);

    }

    function testCreateFileWithDefaults()
    {
        $file = File::write('XX');
        $this->assertNull($file->filename);
        $this->assertEquals(GridFs::$defaultChunkSize, $file->chunk_size);
        $this->assertEquals(2, $file->length);
    }

    /**
     * @dataProvider writeModesProvider()
     */
    function testCreateModelIfNotExistsYetWhenMode($mode)
    {

        $file = new File();
        $this->assertFalse($file->exists);
        $file->stream($mode);
        $this->assertTrue($file->exists);

    }

    /**
     * @dataProvider modesNeedToThrowExceptionIfNotFileProvider()
     */
    function testExceptionReadModeOnEmptyFile($mode)
    {

        $this->expectException(\ErrorException::class);
        $file = new File();
        $file->stream($mode);

    }

    function testExceptionReadPlusModeOnEmptyFile()
    {

        $this->expectException(\ErrorException::class);
        $file = new File();
        $file->stream('r+');

    }

    /**
     * @dataProvider writeModesProvider
     * @dataProvider readModesProvider
     */
    function testExpectTruncateFileOnlyOnWMode($mode)
    {


        $file = new File();
        fwrite($file->stream('w'), 'ABCD');

        $file->stream($mode);

        if (in_array($mode, ['w', 'w+'])) {
            $this->assertEquals(0, $file->length);
            $this->assertEquals(0, $file->chunks()->count());
        } else {
            $this->assertEquals(4, $file->length);
            $this->assertEquals(1, $file->chunks()->count());
        }

    }

    /**
     * @dataProvider readModesProvider
     */
    function testReadStream($mode)
    {

        $file = new File();
        fwrite($file->stream('w'), 'AAAAAAAAAAAAA');

        $result = fread($file->stream($mode), 1000);
        $this->assertEquals($mode != 'w+' ? 'AAAAAAAAAAAAA' : '', $result);;

    }

    /**
     * @dataProvider readModesProvider()
     */
    function testReadPointer($mode)
    {
        $file = new File();
        fwrite($file->stream('w'), 'ABCD');

        $readStream = $file->stream($mode);

        if ($mode != 'w+') {
            $this->assertEquals('A', fread($readStream, 1));
            $this->assertEquals('B', fread($readStream, 1));
            $this->assertEquals('C', fread($readStream, 1));
            $this->assertEquals('D', fread($readStream, 1));
            $this->assertEquals('', fread($readStream, 1));
        } else {
            $this->assertEquals('', fread($readStream, 1));
        }

    }

    /**
     * @dataProvider writeModesProvider
     */
    function testWritePointer($mode)
    {
        $file = new File();
        $writeStream = $file->stream($mode);
        fwrite($writeStream, 'A');
        fwrite($writeStream, 'B');
        fwrite($writeStream, 'C');
        fwrite($writeStream, 'D');
        fwrite($writeStream, '');
        fclose($writeStream);

        $this->assertEquals('ABCD', fread($file->stream('r'), 5));

    }


    /**
     * @dataProvider appendModeProviders
     */
    function testWriteStreamAppendMode($mode)
    {

        $file = new File();
        fwrite($file->stream('w'), 'AAAA');
        fwrite($file->stream($mode), 'BBBBB');

        $this->assertEquals('AAAABBBBB', fread($file->stream('r'), 50));

    }

    /**
     * @dataProvider appendModeProviders
     */
    function testReadInAppendMode($mode)
    {
        $file = new File();
        fwrite($file->stream('w'), 'ABCD');

        if ($mode === 'a') {
            $this->expectNotToPerformAssertions();
            return;
        }
        $this->assertEquals('AB', fread($file->stream($mode), 2));
    }

    /**
     * @dataProvider onlyWriteModeProvider
     */
    function testReturnFalseIfWrongModeRead($mode)
    {
        $file = new File();
        fwrite($file->stream('w'), 'ABCD');

        $this->assertFalse(fread($file->stream($mode), 2));
    }


    public function writeModesProvider(): array
    {
        return [
            ['a'], ['a+'], ['w'], [ 'w+']
        ];
    }

    public function readModesProvider(): array
    {
        return [
            ['r' => 'r'], ['a+' => 'a+'], ['r+' => 'r+'], ['w+' => 'w+']
        ];
    }

    public function onlyWriteModeProvider(): array
    {
        return [
            ['w', 'a']
        ];
    }

    public function modesNeedToThrowExceptionIfNotFileProvider(): array
    {
        return [
            ['r'], ['r+']
        ];
    }

    public function appendModeProviders(): array
    {
        return [
            ['a'], ['a+']
        ];
    }

}
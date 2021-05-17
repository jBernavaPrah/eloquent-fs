<?php


namespace Tests;


use Carbon\Carbon;
use JBernavaPrah\EloquentFS\Models\File;


class EloquentFSStreamWrapperTest extends TestCase
{


    function testCantTruncateFileYet()
    {


        $this->expectError();
        file_put_contents('efs://some_file.txt', 'foobar');

        $stream = fopen('efs://some_file.txt', 'r');
        ftruncate($stream, 4);
//
//        /** @var File $file */
//        $file = File::first();
//        $this->assertEquals(4, $file->length);
//
//
//        ftruncate($stream, 6);
//
//        /** @var File $file */
//        $file = File::first();
//        $this->assertEquals(6, $file->length);
//
//        $this->assertEquals('foob' . str_repeat(chr(0), 2), file_get_contents('efs://some_file.txt'));
//
//        ftruncate($stream, 0);
//
//        /** @var File $file */
//        $file = File::first();
//        $this->assertEquals(0, $file->length);
//
//        ftruncate($stream, 10);
//
//        /** @var File $file */
//        $file = File::first();
//        $this->assertEquals(10, $file->length);
//
//        $this->assertEquals(str_repeat(chr(0), 10), file_get_contents('efs://some_file.txt'));

    }

    function testCreateEmptyFile()
    {

        Carbon::setTestNow(Carbon::now());

        touch('efs://some_file.php');
        $this->assertEquals(1, File::count());
        $this->assertEquals(Carbon::now()->timestamp, File::first()->updated_at->timestamp);


        touch('efs://some_file.php', Carbon::now()->addSeconds(15)->timestamp);
        $this->assertEquals(1, File::count());
        $this->assertEquals(Carbon::now()->addSeconds(15)->timestamp, File::first()->updated_at->timestamp);

    }

    /**
     * @dataProvider provideModeRP
     * @dataProvider provideModeR
     */
    function testFileNeedToExists($mode)
    {
        $this->expectError();
        fopen('efs://some_file.php', $mode);
    }

    /**
     * @dataProvider provideModeW
     * @dataProvider provideModeA
     */
    function testErrorIfWrongModeForRead($mode)
    {

        file_put_contents('efs://test_file.txt', 'foobar');

        $this->expectError();
        $stream = fopen('efs://some_file.php', $mode);
        fread($stream, 10);
        fclose($stream);

    }


    /**
     * @dataProvider provideModeW
     * @dataProvider provideModeWP
     */
    function testExpectTruncateFileOnlyOnWMode($mode)
    {

        file_put_contents('efs://test_file.txt', 'foobar');

        // reopen it..
        fopen('efs://test_file.txt', $mode);

        $file = File::first();
        $this->assertEquals(0, $file->length);
        $this->assertEquals(0, $file->chunks()->count());

    }

    /**
     * @dataProvider provideModeA
     * @dataProvider provideModeAP
     * @dataProvider provideModeR
     * @dataProvider provideModeRP
     */
    function testExpectedFileLeftAsIs($mode)
    {

        file_put_contents('efs://test_file.txt', 'foobar');

        // reopen it..
        fopen('efs://test_file.txt', $mode);

        $file = File::first();
        $this->assertEquals(6, $file->length);
        $this->assertEquals(1, $file->chunks()->count());


    }

    function testStreamRead()
    {

        file_put_contents('efs://test_file.txt', 'foobar');

        $stream = fopen('efs://test_file.txt', 'a+');
        $this->assertEquals('foobar', fread($stream, 100));
        $stream = fopen('efs://test_file.txt', 'r+');
        $this->assertEquals('foobar', fread($stream, 100));
        $stream = fopen('efs://test_file.txt', 'r');
        $this->assertEquals('foobar', fread($stream, 100));


    }

    function testStreamUnlink()
    {
        file_put_contents('efs://test_file.txt', 'foobar');

        $this->assertEquals(1, File::count());
        $this->assertTrue(unlink('efs://test_file.txt'));
        $this->assertEquals(0, File::count());

    }

    /**
     * @dataProvider provideModeR
     * @dataProvider provideModeRP
     * @dataProvider provideModeAP
     */
    function testReadPointer($mode)
    {

        file_put_contents('efs://test_file.txt', 'foo');


        $stream = fopen('efs://test_file.txt', $mode);
        $this->assertEquals('f', fread($stream, 1));
        $this->assertEquals(1, ftell($stream));
        $this->assertFalse(feof($stream));

        $this->assertEquals('o', fread($stream, 1));
        $this->assertEquals(2, ftell($stream));
        $this->assertFalse(feof($stream));

        $this->assertEquals('o', fread($stream, 1));
        $this->assertEquals(3, ftell($stream));
        $this->assertTrue(feof($stream));

        $this->assertEquals('', fread($stream, 1));
        $this->assertEquals(3, ftell($stream));
        $this->assertTrue(feof($stream));


    }


    function testTellWorkAlsoWithNotFlushedFiles()
    {

        $stream = fopen('efs://test_file.txt', 'w');
        fwrite($stream, 'foobar');

        $this->assertEquals(6, ftell($stream));

    }


    /**
     * @dataProvider provideModeAP
     * @dataProvider provideModeA
     * @dataProvider provideModeR
     * @dataProvider provideModeRP
     */
    function testSeek($mode)
    {

        file_put_contents('efs://test_file.txt', 'foobar');

        $readStream = fopen('efs://test_file.txt', $mode);

        $this->assertEquals(-1, fseek($readStream, -1));
        $this->assertEquals(-1, fseek($readStream, 7));
        $this->assertEquals(-1, fseek($readStream, 1, SEEK_END));
        $this->assertEquals(-1, fseek($readStream, -7, SEEK_END));
        $this->assertEquals(-1, fseek($readStream, 7, SEEK_CUR));
        $this->assertEquals(-1, fseek($readStream, -1, SEEK_CUR));

        $this->assertEquals(0, fseek($readStream, 1));
        $this->assertEquals(0, fseek($readStream, 0));
        $this->assertEquals(0, fseek($readStream, 4));

        $this->assertEquals(0, fseek($readStream, 0, SEEK_END));
        $this->assertEquals(0, fseek($readStream, -3, SEEK_END));

        $this->assertEquals(0, fseek($readStream, 0));
        $this->assertEquals(0, fseek($readStream, 3, SEEK_CUR));


    }

    /**
     * @dataProvider provideModeWP
     * @dataProvider provideModeW
     * @dataProvider provideModeA
     * @dataProvider provideModeAP
     * @dataProvider provideModeRP
     */
    function testWrite($mode)
    {

        touch('efs://test_file.txt');
        $stream = fopen('efs://test_file.txt', $mode);
        fwrite($stream, 'A');
        fwrite($stream, 'B');
        fwrite($stream, 'C');
        fwrite($stream, 'D');
        fwrite($stream, '');
        fclose($stream);

        $stream = fopen('efs://test_file.txt', 'r');
        $this->assertEquals('ABCD', fread($stream, 5));

    }

    /**
     * @dataProvider provideModeA
     * @dataProvider provideModeAP
     */
    function testWriteStreamAppendMode($mode)
    {

        $stream = fopen('efs://test_file.txt', $mode);
        fwrite($stream, 'a');
        fclose($stream);

        $stream = fopen('efs://test_file.txt', $mode);
        fwrite($stream, 'b');
        fclose($stream);

        $stream = fopen('efs://test_file.txt', $mode);
        fwrite($stream, 'c');
        fclose($stream);

        $this->assertEquals('abc', file_get_contents('efs://test_file.txt'));

    }

    function testBigFileMD5()
    {

        $strLength = 300000;
        $resource = tmpfile();
        $tmpFilename = stream_get_meta_data($resource)['uri'];

        $data = function ($length) {
            $data = '';
            while ($length > 0) {
                --$length;
                $data .= chr(rand(0, 255));
            }
            return $data;
        };
        $data = $data($strLength);
        file_put_contents($tmpFilename, $data);

        copy($tmpFilename, 'efs://test_file.txt');

        $savedData = file_get_contents('efs://test_file.txt');
        $this->assertEquals($strLength, strlen($savedData));

        foreach (str_split($savedData) as $key => $char) {
            $this->assertEquals($data[$key], $char, "Not Equal on character: $key");
        }

        $this->assertEquals(md5($data), md5($savedData));
        $this->assertEquals($data, $savedData);


    }

    /**
     * @dataProvider provideModeAP
     */
    function testReadInAppendMode($mode)
    {
        $stream = fopen('efs://some_file.txt', 'w');
        fwrite($stream, 'ABCD');
        fclose($stream);

        $stream = fopen('efs://some_file.txt', $mode);
        fwrite($stream, 'EFGH');

        $this->assertEquals('AB', fread($stream, 2));
    }


    public function provideModeW(): array
    {
        return [['w']];
    }

    public function provideModeWP(): array
    {
        return [['w+']];
    }

    public function provideModeA(): array
    {
        return [['a']];
    }

    public function provideModeAP(): array
    {
        return [['a+']];
    }

    public function provideModeR(): array
    {
        return [['r']];
    }

    public function provideModeRP(): array
    {
        return [['r+']];
    }


}
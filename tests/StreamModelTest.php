<?php


namespace Tests;


use JBernavaPrah\EloquentFS\Models\File;


class StreamModelTest extends TestCase
{

    /**
     * @dataProvider writeModesProvider()
     */
    function testCreateModelIfNotExistsYetWhenModeRequireIt($mode)
    {

        $file = new File();
        $this->assertFalse($file->exists);
        $file->open($mode);
        $this->assertTrue($file->exists);

    }

    /**
     * @dataProvider modesNeedToThrowExceptionIfNotFileProvider()
     */
    function testExceptionReadModeOnEmptyFile($mode)
    {

        $this->expectError();
        $file = new File();
        $file->open($mode);

    }

    function testExceptionReadPlusModeOnEmptyFile()
    {

        $this->expectError();
        $file = new File();
        $file->open('r+');

    }

    /**
     * @dataProvider writeModesProvider
     * @dataProvider readModesProvider
     */
    function testExpectTruncateFileOnlyOnWMode($mode)
    {


        $file = new File();
        fwrite($file->open('w'), 'ABCD');

        $file->open($mode);

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
        fwrite($file->open('w'), 'AAAAAAAAAAAAA');

        $result = fread($file->open($mode), 1000);
        $this->assertEquals($mode != 'w+' ? 'AAAAAAAAAAAAA' : '', $result);

    }

    /**
     * @dataProvider readModesProvider()
     */
    function testReadPointer($mode)
    {
        $file = new File();
        fwrite($file->open('w'), 'ABCD');

        $readStream = $file->open($mode);

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
     * @dataProvider tellProvider
     */
    function testTell($mode, $position)
    {

        $file = new File();
        fwrite($file->open('w'), 'ABCD');

        $this->assertEquals($position, ftell($file->open($mode)));

    }

    /**
     * @dataProvider tellProviderAfterReadOneCharacter
     */
    function testTellAfterReadOneCharacter($mode, $position)
    {
        $file = new File();

        fwrite($file->open('w'), 'ABCD');
        $stream = $file->open($mode);
        fread($stream, 1);
        $this->assertEquals($position, ftell($stream));

    }

    /**
     * @dataProvider readModesProvider
     * @dataProvider writeModesProvider
     */
    function testSeek($mode)
    {
        $file = new File();
        fwrite($file->open('w'), 'ABCD');

        $readStream = $file->open($mode);

        $this->assertEquals(-1, fseek($readStream, -1));
        $this->assertEquals(-1, fseek($readStream, 5));
        $this->assertEquals(-1, fseek($readStream, 1, SEEK_END));
        $this->assertEquals(-1, fseek($readStream, -5, SEEK_END));
        $this->assertEquals(-1, fseek($readStream, 5, SEEK_CUR));
        $this->assertEquals(-1, fseek($readStream, -1, SEEK_CUR));

        // the file is truncated, therefore is this fseek will always return -1 for w and w+
        if (!in_array($mode, ['w', 'w+'])) {
            $this->assertEquals(0, fseek($readStream, 1));
            $this->assertEquals(0, fseek($readStream, 0));
            $this->assertEquals(0, fseek($readStream, 4));

            $this->assertEquals(0, fseek($readStream, 0, SEEK_END));
            $this->assertEquals(0, fseek($readStream, -3, SEEK_END));

            $this->assertEquals(0, fseek($readStream, 0));
            $this->assertEquals(0, fseek($readStream, 3, SEEK_CUR));

        }

    }

    /**
     * @dataProvider writeModesProvider
     */
    function testWritePointer($mode)
    {
        $file = new File();
        $writeStream = $file->open($mode);
        fwrite($writeStream, 'A');
        fwrite($writeStream, 'B');
        fwrite($writeStream, 'C');
        fwrite($writeStream, 'D');
        fwrite($writeStream, '');
        fclose($writeStream);

        $this->assertEquals('ABCD', fread($file->open('r'), 5));

    }

    /**
     * @dataProvider appendModeProviders
     */
    function testWriteStreamAppendMode($mode)
    {

        $file = new File();
        $stream = $file->open('w');
        fwrite($stream, 'AAAAc');
        fclose($stream);

        $stream = $file->open($mode);
        fwrite($stream, 'BBBBB');
        fclose($stream);

        $this->assertEquals('AAAAcBBBBB', fread($file->open('r'), 50));

    }

    function testBigFileMD5()
    {

        $resource = tmpfile();
        $meta = stream_get_meta_data($resource);
        $tmpFilename = $meta['uri'];

        $strLength = 300000;

        $data = '';
        $byteSize = $strLength;
        while ($byteSize > 0) {
            --$byteSize;
            $data .= chr(rand(0, 255));
        }

        file_put_contents($tmpFilename, $data);
        $md5 = md5($data);

        $file = new File();
        stream_copy_to_stream($resource, $file->open('w+'));
        $file->seek(0);

        $returnData = $file->read($strLength + 1);
        $this->assertEquals($strLength, strlen($returnData));

        foreach (str_split($returnData) as $key => $char) {
            $this->assertEquals($data[$key], $char, "Not Equal on character: $key");
        }

        $this->assertEquals($md5, md5($returnData));
        $this->assertEquals($data, $returnData);


    }

    /**
     * @dataProvider appendModeProviders
     */
    function testReadInAppendMode($mode)
    {
        $file = new File();
        fwrite($file->open('w'), 'ABCD');

        if ($mode === 'a') {
            $this->expectNotToPerformAssertions();
            return;
        }
        $this->assertEquals('AB', fread($file->open($mode), 2));
    }

    /**
     * @dataProvider onlyWriteModeProvider
     */
    function testReturnFalseIfWrongModeRead($mode)
    {
        $file = new File();
        fwrite($file->open('w'), 'ABCD');

        $this->assertFalse(fread($file->open($mode), 2));
    }


    public function writeModesProvider(): array
    {
        return [
            ['a'], ['a+'], ['w'], ['w+']
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

    public function tellProvider(): array
    {
        return [
            ['a', 0],
            ['a+', 0],
            ['r+', 0],
            ['r', 0],
            ['w', 0],
            ['w+', 0],
        ];
    }

    public function tellProviderAfterReadOneCharacter(): array
    {
        return [
            ['a+', 1],
            ['r+', 1],
            ['r', 1],
            ['w+', 0],
        ];
    }

}
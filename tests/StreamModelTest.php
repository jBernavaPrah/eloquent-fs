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
        $file->stream($mode);
        $this->assertTrue($file->exists);

    }

    /**
     * @dataProvider modesNeedToThrowExceptionIfNotFileProvider()
     */
    function testExceptionReadModeOnEmptyFile($mode)
    {

        $this->expectError();
        $file = new File();
        $file->stream($mode);

    }

    function testExceptionReadPlusModeOnEmptyFile()
    {

        $this->expectError();
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
        $this->assertEquals($mode != 'w+' ? 'AAAAAAAAAAAAA' : '', $result);

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
     * @dataProvider tellProvider
     */
    function testTell($mode, $position)
    {

        $file = new File();
        fwrite($file->stream('w'), 'ABCD');

        $this->assertEquals($position, ftell($file->stream($mode)));

    }

    /**
     * @dataProvider tellProviderAfterReadOneCharacter
     */
    function testTellAfterReadOneCharacter($mode, $position)
    {
        $file = new File();

        fwrite($file->stream('w'), 'ABCD');
        $stream = $file->stream($mode);
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
        fwrite($file->stream('w'), 'ABCD');

        $readStream = $file->stream($mode);

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
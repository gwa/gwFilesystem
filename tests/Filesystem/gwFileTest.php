<?php
use Gwa\Filesystem\gwFile;
use Gwa\Filesystem\gwDirectory;

class gwFileTest extends PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $file = new gwFile('');
        $this->assertInstanceOf('Gwa\Filesystem\gwFile', $file);
        $this->assertFalse($file->exists());
    }

    public function testExists()
    {
        $file = new gwFile(__FILE__);
        $this->assertTrue($file->exists());
    }

    public function testReadableWritable()
    {
        $file = new gwFile(__FILE__);
        $this->assertTrue($file->isReadable());
        $this->assertTrue($file->isWritable());
    }

    public function testCreateFile()
    {
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $this->assertFalse($file->exists());
        $this->assertTrue($file->isWritable());
        $byteswritten = $file->appendContent('foo');
        $this->assertEquals(3, $byteswritten);
    }

    public function testReadFile()
    {
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $this->assertTrue($file->exists());
        $this->assertTrue($file->isReadable());
        $content = $file->getContent();
        $this->assertEquals('foo', $content);
    }

    public function testReplaceContent()
    {
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $content = $file->replaceContent('bar');
        $content = $file->getContent();
        $this->assertEquals('bar', $content);
    }

    public function testAppendContent()
    {
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $content = $file->appendContent('foo');
        $content = $file->getContent();
        $this->assertEquals('barfoo', $content);
    }

    public function testGetMimeTypeAndEncoding()
    {
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $this->assertEquals('text/plain', $file->getMimeType());
    }

    public function testGetDownloadHeaders()
    {
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $headers = $file->getDownloadHeaders();
        $this->assertTrue(array_key_exists('Content-disposition', $headers));
        $this->assertEquals($file->getMimeType(true), $headers['Content-type']);
    }

    public function testIsImage()
    {
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $this->assertFalse($file->isImage());

        $path = realpath(__DIR__.'/../img').'/octopus.jpeg';
        $file = new gwFile($path);
        $this->assertTrue($file->isImage());
    }

    public function testGetPath()
    {
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $this->assertEquals($path, $file->getPath());
        $this->assertEquals(__DIR__.'/../temp', $file->getDirPath());
        $this->assertEquals('tempfile', $file->getBasename());
        $this->assertEquals('', $file->getExtension());
    }

    public function testMoveTo()
    {
        $dir = gwDirectory::makeDirectoryRecursive(__DIR__.'/../temp/subfolder');
        $path = __DIR__.'/../temp/tempfile';
        $file = new gwFile($path);
        $file->moveTo($dir);
    }

    public function testDelete()
    {
        $path = __DIR__.'/../temp/subfolder/tempfile';
        $file = new gwFile($path);
        $this->assertTrue($file->exists());
        $file->delete();
        $this->assertFalse($file->exists());
    }
}

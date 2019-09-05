<?php

namespace FastLaravel\Http\Tests\Util;

use Mockery;
use FastLaravel\Http\Tests\TestCase;
use FastLaravel\Http\Util\File;

class FileTest extends TestCase
{
    protected static $basePath =  __DIR__ . '/../fixtures';

    public function testGetSuffix()
    {
        $this->assertEquals(File::getSuffix(__FILE__), '.php');
        $this->assertEquals(File::getSuffix(__FILE__, true), 'php');
    }

    public function testIsAbsPath()
    {
        $this->assertTrue(File::isAbsPath(__FILE__));
        $this->assertFalse(File::isAbsPath("./FileTest.php"));
    }

    public function testMd5File()
    {
        $md5 = File::md5File(__DIR__);
        $this->assertTrue(strlen($md5) === 32);
    }

    public function testCreateDirectory()
    {
        $dirPath = static::$basePath . "/laravel/test/test/test";
        $this->assertTrue(File::createDirectory($dirPath, 0644));
    }

    public function testCleanDirectory()
    {
        $dirPath = static::$basePath . "/laravel/test/test";
        $this->assertTrue(File::cleanDirectory($dirPath));
    }

    public function testDeleteDirectory()
    {
        $dirPath = static::$basePath . "/laravel/test/test";
        $this->assertTrue(File::deleteDirectory($dirPath));
    }

    public function testCopyDirectory()
    {
        $dirPath = static::$basePath . "/laravel/test";
        $this->assertTrue(File::copyDirectory($dirPath, $dirPath . "1"));
    }

    public function testMoveDirectory()
    {
        $dirPath = static::$basePath . "/laravel/test";
        $this->assertTrue(File::moveDirectory($dirPath . "1", $dirPath . "/a"));
    }

    public function testTouchFile()
    {
        $dirPath = static::$basePath . "/laravel/test";
        $this->assertTrue(File::touchFile($dirPath . "/a.txt"));
    }

    public function testCopyFile()
    {
        $dirPath = static::$basePath . "/laravel/test";
        $this->assertTrue(File::copyFile($dirPath . "/a.txt", $dirPath . "/b.txt"));
    }

    public function testCreateFile()
    {
        $dirPath = static::$basePath . "/laravel/test";
        $this->assertTrue(File::createFile($dirPath . "/c.txt", 'hello world'));
    }

    public function testMoveFile()
    {
        $dirPath = static::$basePath . "/laravel/test";
        $this->assertTrue(File::moveFile($dirPath . "/c.txt", $dirPath . "/d.txt"));
    }

    public function testScanDirectory()
    {
        $dirPath = static::$basePath . "/laravel/test";
        $result = File::scanDirectory($dirPath);
        $this->assertTrue(count($result) === 2);
        $this->assertTrue(isset($result['files']));
        $this->assertTrue(isset($result['dirs']));
        $this->assertTrue(File::deleteDirectory($dirPath));
    }
}
<?php
namespace FastLaravel\Http\Tracker\Factory;

use FastLaravel\Http\Tracker\SaverInterface;
use FastLaravel\Http\Util\File as UtilFile;

class File implements SaverInterface
{
    protected $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function save($profile)
    {
        $json = json_encode($profile);
        return UtilFile::createFile($this->file, $json.PHP_EOL);
    }
}
<?php

namespace FastLaravel\Http\Tests\Util;

use Mockery;
use FastLaravel\Http\Tests\TestCase;
use FastLaravel\Http\Util\JsonHelper;

class JsonHelperTest extends TestCase
{
    private $json = null;

    private $data = null;

    public function setUp()
    {
        $this->data = [
            'server' => $_SERVER,
            'token' => token_get_all(file_get_contents(__FILE__))
        ];
        $this->json = json_encode($this->data);
    }

    public function testEncode()
    {
        $this->assertEquals(JsonHelper::encode($this->data), $this->json);
    }

    public function testDecode()
    {
        $this->assertEquals(JsonHelper::decode($this->json, true), json_decode($this->json, true));
    }

}
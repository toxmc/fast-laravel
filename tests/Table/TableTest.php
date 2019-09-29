<?php

namespace Tests\Table;

use Mockery as m;
use Swoole\Table;
use FastLaravel\Http\Table\SwooleTable;
use FastLaravel\Http\Tests\TestCase;

class TableTest extends TestCase
{
    public function testAdd()
    {
        $table = m::mock(Table::class);

        $swooleTable = new SwooleTable();
        $swooleTable->add($name = 'foo', $table);

        $this->assertSame($table, $swooleTable->get($name));
    }

    public function testDel()
    {
        $table = m::mock(Table::class);

        $swooleTable = new SwooleTable();
        $swooleTable->add($name = 'foo', $table);
        $this->assertSame($table, $swooleTable->get($name));
        $swooleTable->del($name);
        $this->assertSame(null, $swooleTable->get($name));
    }

    public function testGetAll()
    {
        $table = m::mock(Table::class);

        $swooleTable = new SwooleTable();
        $swooleTable->add($foo = 'foo', $table);
        $swooleTable->add($bar = 'bar', $table);

        $this->assertSame(2, count($swooleTable->getAll()));
        $this->assertSame(2, $swooleTable->count());
        $this->assertSame($table, $swooleTable->getAll()[$foo]);
        $this->assertSame($table, $swooleTable->getAll()[$bar]);
    }

    public function testDynamicallyGet()
    {
        $table = m::mock(Table::class);

        $swooleTable = new SwooleTable();
        $swooleTable->add($foo = 'foo', $table);

        $this->assertSame($table, $swooleTable->$foo);
    }
}

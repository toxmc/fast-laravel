<?php

namespace FastLaravel\Http\Facade\Swoole;

use FastLaravel\Http\Table\SwooleTable;
use Illuminate\Support\Facades\Facade;

/**
 * Class Table
 * @package FastLaravel\Http\Facade\Swoole
 * @method static SwooleTable add(string $name, Table $table)
 * @method static \Swoole\Table get(string $name)
 * @method static true del(string $name)
 * @method static int count()
 * @method static array getAll();
 * @method static \Swoole\Table __get(callable $callback);
 */
class Table extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'swoole.table';
    }
}
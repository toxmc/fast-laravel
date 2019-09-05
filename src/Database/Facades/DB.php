<?php

namespace FastLaravel\Http\Database\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \FastLaravel\Http\Database\ConnectionInterface connection(string $name = null)
 * @method static string getDefaultConnection()
 * @method static void setDefaultConnection(string $name)
 * @method static \FastLaravel\Http\Database\Query\Builder table(string $table)
 * @method static \FastLaravel\Http\Database\Query\Expression raw($value)
 * @method static mixed selectOne(string $query, array $bindings = [])
 * @method static mixed fetchOne(string $query, array $bindings = [])
 * @method static array select(string $query, array $bindings = [])
 * @method static array fetchAll(string $query, array $bindings = [])
 * @method static bool insert(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static bool statement(string $query, array $bindings = [])
 * @method static int affectingStatement(string $query, array $bindings = [])
 * @method static bool unprepared(string $query)
 * @method static array prepareBindings(array $bindings)
 * @method static mixed transaction(\Closure $callback, int $attempts = 1)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 * @method static int transactionLevel()
 * @method static array pretend(\Closure $callback)
 *
 * @see \Illuminate\Database\DatabaseManager
 * @see \Illuminate\Database\Connection
 */
class DB extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'pool.db';
    }
}
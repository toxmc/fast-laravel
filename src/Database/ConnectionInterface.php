<?php

namespace FastLaravel\Http\Database;

use Closure;

interface ConnectionInterface
{

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string $table
     * @return \FastLaravel\Http\Database\Query\Builder
     */
    public function table($table);

    /**
     * Get a new raw query expression.
     *
     * @param  mixed $value
     * @return \FastLaravel\Http\Database\Query\Expression
     */
    public function raw($value);

    /**
     * Run a select statement and return a single result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return mixed
     */
    public function selectOne($query, array $bindings = []);

    /**
     * Run a select statement and return a single result.
     *
     * @param  string $query
     * @param  array $bindings
     * @return mixed
     */
    public function fetchOne($query, array $bindings = array());

    /**
     * Run a select statement against the database.
     *
     * @param  string $query
     * @param  array $bindings
     * @return array
     */
    public function fetch($query, array $bindings = array());

    /**
     * Run a select statement against the database.
     *
     * @param  string $query
     * @param  array $bindings
     * @return array
     */
    public function fetchAll($query, array $bindings = array());

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     */
    public function select($query, array $bindings = []);

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function insert($query, $bindings = []);

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function update($query, $bindings = []);

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function delete($query, $bindings = []);

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string $query
     * @param  array $bindings
     * @return \PDOStatement
     */
    public function query($query, array $bindings = array());

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return \PDOStatement
     */
    public function statement($query, $bindings = []);

    /**
     * Return the auto-increment ID of the last inserted row
     *
     * @param null $name
     * @return mixed|string
     */
    public function lastInsertId($name = null);

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array $bindings
     * @return array
     */
    public function prepareBindings(array $bindings);

    /**
     * Execute a Closure within a transaction.
     *
     * @param  \Closure $callback
     * @return mixed
     *
     * @throws \Exception
     */
    public function transaction(Closure $callback);

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction();

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit();

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack();

    /**
     * Checks the connection to see if there is an active transaction
     *
     * @return int
     */
    public function inTransaction();

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  \Closure $callback
     * @return array
     */
    public function pretend(Closure $callback);

}

<?php

namespace FastLaravel\Http\Traits;

use Swoole\Table;
use FastLaravel\Http\Table\SwooleTable;

/**
 * 创建配置中配置的table集合
 *
 * Trait TableTrait
 *
 * @package FastLaravel\Http\Traits
 */
trait TableTrait
{
    /**
     * @var \FastLaravel\Http\Server\Table
     */
    protected $table;

    /**
     * Register customized swoole tables.
     */
    protected function createTables()
    {
        $this->table = new SwooleTable;

        $tables = $this->container['config']->get('swoole_http.tables', []);
        foreach ($tables as $key => $value) {
            $table = new Table($value['size']);
            $columns = $value['columns'] ?? [];
            foreach ($columns as $column) {
                if (isset($column['size'])) {
                    $table->column($column['name'], $column['type'], $column['size']);
                } else {
                    $table->column($column['name'], $column['type']);
                }
            }
            $table->create();

            $this->table->add($key, $table);
        }
    }

    /**
     * Bind swoole table to Laravel app container.
     */
    protected function bindTable()
    {
        $this->app->singleton('swoole.table', function () {
            return $this->table;
        });
    }
}

<?php
namespace FastLaravel\Http\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * Class Debug
 * @package FastLaravel\Http\Facade
 * @method static add(mixed $message)
 * @method static getAll()
 * @method static reset()
 */
class Debug extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'context.debug';
    }
}
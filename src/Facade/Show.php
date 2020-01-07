<?php
namespace FastLaravel\Http\Facade;

use Illuminate\Support\Facades\Facade;
use FastLaravel\Http\Output\Output;

/**
 * Class Debug
 * @package FastLaravel\Http\Facade
 * @method static int writeln($messages = '', $newline = true, $quit = false)
 * @method static int writeLogo()
 * @method static int colored(string $text, string $tag = 'info', $quit=false)
 * @method static int writeList(array $list, $titleStyle = 'comment', string $cmdStyle = 'info', string $descStyle = null)
 * @method static int writeItems(array $items, string $cmdStyle)
 * @method static int info(String $messages, $quit = false)
 * @method static int note(String $messages, $quit = false)
 * @method static int notice(String $messages, $quit = false)
 * @method static int success(String $messages, $quit = false)
 * @method static int primary(String $messages, $quit = false)
 * @method static int warning(String $messages, $quit = false)
 * @method static int danger(String $messages, $quit = false)
 * @method static int error(String $messages, $quit = false)
 * @method static int magenta(String $messages, $quit = false)
 */
class Show extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return Output;
     */
    protected static function getFacadeAccessor()
    {
        return \output();
    }
}
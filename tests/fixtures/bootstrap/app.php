<?php

use Illuminate\Config\Repository;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Container\Container;
use FastLaravel\Http\Context\Debug;
use FastLaravel\Http\Util\Logger;

//$kernel = new TestKernel();
$kernel = Mockery::mock(TestKernel::class);
$kernel->shouldReceive('bootstrappers')->times()->andReturn([]);

$kernel->shouldReceive('terminate')->times()->andReturn(true);

$app = Mockery::mock(Container::class);
$app->shouldReceive('make')
    ->with(Kernel::class)
    ->once()
    ->andReturn($kernel);

$app->shouldReceive('bootstrapWith')
    ->once()
    ->andReturn($kernel);

$resolves = [
    'view', 'files', 'session', 'session.store', 'routes',
    'db', 'db.factory', 'cache', 'cache.store', 'cookie','config',
    'encrypter', 'hash', 'router', 'translator', 'url', 'log'
];

foreach ($resolves as $resolve) {
    $app->shouldReceive('offsetExists')
        ->with($resolve)
        ->times()
        ->andReturn(true);
    if ($resolve == 'config') {
        $config = new Repository([]);
        $app->shouldReceive('make')
            ->with($resolve)
            ->times()->andReturn($config);
    } else {
        $app->shouldReceive('make')
            ->with($resolve)
            ->times();
    }
}

$app->shouldReceive('instance')
    ->with('context.debug', Debug::class);


$app->shouldReceive('singleton');
$app->shouldReceive('alias');

return $app;

class TestKernel
{
    public function bootstrappers()
    {
        return [];
    }

}

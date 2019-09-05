<?php

namespace FastLaravel\Http\Tests;

use Illuminate\Support\Facades\Facade;
use Mockery as m;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * call private or protected variable
     * @param $object
     * @param $variable
     * @return \Closure
     */
    protected function callVariable($object, $variable)
    {
        $var = function () use($variable) {
            return $this->$variable;
        };
        $variable = $var->bindTo($object, $object);
        return $variable();
    }

    /**
     * call private or protected method
     * @param $object
     * @param $method
     * @param $args
     * @return mixed
     */
    protected function callFunction($object, $method, ...$args)
    {
        $newMethod = function () use ($method, $args) {
            if ($args) {
                // ... 放形参中代表，参数转数组，放实参中，数组转参数
                return $this->{$method}(...$args);
            } else {
                return $this->{$method}();
            }
        };
        $method = $newMethod->bindTo($object, $object);
        return $method();
    }

    public function tearDown()
    {
        $this->addToAssertionCount(
            m::getContainer()->mockery_getExpectationCount()
        );

        Facade::clearResolvedInstances();
        parent::tearDown();
        m::close();
        Mock::disableAll();
    }

    protected function mockMethod($name, \Closure $function, $namespace = null)
    {
        $builder = new MockBuilder;
        $builder->setNamespace($namespace)
                ->setName($name)
                ->setFunction($function);

        $mock = $builder->build();
        $mock->enable();
    }
}

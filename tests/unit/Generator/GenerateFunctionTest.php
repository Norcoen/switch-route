<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests\Generator;

use Closure;
use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator\GenerateFunction;
use Jasny\SwitchRoute\InvalidRouteException;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\Tests\RoutesTrait;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * @covers \Jasny\SwitchRoute\Generator\GenerateFunction
 * @covers \Jasny\SwitchRoute\Generator\AbstractGenerate
 */
class GenerateFunctionTest extends TestCase
{
    use RoutesTrait;

    protected function getRouteArgs()
    {
        $routes = $this->getRoutes();

        $routeArgs = [];
        $isClosure = $this->isInstanceOf(Closure::class);

        foreach ($routes as $key => $route) {
            if (isset($route['include'])) {
                continue;
            }

            $routeArgs[] = [$route, $isClosure];
        }

        return $routeArgs;
    }

    public function test()
    {
        $routes = $this->getRoutes();
        $routeArgs = $this->getRouteArgs();
        $structure = $this->getStructure();

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->exactly(count($routeArgs)))->method('generateInvocation')
            ->withConsecutive(...$routeArgs)
            ->willReturnCallback(function ($route, callable $genArg) {
                ['controller' => $controller, 'action' => $action] = $route + ['controller' => null, 'action' => null];
                $arg = $action !== 'not-found' ? $genArg('id', '', null) : $genArg('allowedMethods', '', []);
                return sprintf("call('%s', '%s', %s)", $controller, $action, $arg);
            });

        $generate = new GenerateFunction($invoker);

        $code = $generate('route_generate_function_test', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-function-test.phps');
        $this->assertEquals($expected, $code);
    }

    public function testDefault()
    {
        $routes = ['GET /' => ['controller' => 'info']];
        $structure = ["\0" => (new Endpoint('/'))->withRoute('GET', ['controller' => 'info'], [])];

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->once())->method('generateInvocation')
            ->with(['controller' => 'info'], $this->isInstanceOf(Closure::class))
            ->willReturn('info()');
        $invoker->expects($this->once())->method('generateDefault')
            ->willReturn("http_response_code(404);\necho \"Not Found\";");
        $generate = new GenerateFunction($invoker);

        $code = $generate('route_generate_function_test_default', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-function-test-default.phps');
        $this->assertEquals($expected, $code);
    }

    public function testAllVars()
    {
        $routes = ['GET /{foo}/{bar}/{qux}' => ['controller' => 'info']];
        $vars = ['foo' => 0, 'bar' => 1, 'qux' => 2];
        $structure = ["*" => ["*" => ["*" => [
            "\0" => (new Endpoint('/'))->withRoute('GET', ['controller' => 'info'], $vars)
        ]]]];

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->once())->method('generateInvocation')
            ->with(['controller' => 'info'], $this->isInstanceOf(Closure::class))
            ->willReturnCallback(function ($route, callable $genArg) {
                return $genArg(null);
            });
        $invoker->expects($this->once())->method('generateDefault')
            ->willReturn("return false;");

        $generate = new GenerateFunction($invoker);

        $code = $generate('route_generate_function_test_all_vars', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-function-test-all-vars.phps');
        $this->assertEquals($expected, $code);
    }

    public function testWithInvalidRoute()
    {
        $this->expectException(InvalidRouteException::class);
        $this->expectExceptionMessage("Route for 'GET /*' should specify 'include', 'controller', or 'action'");

        $routes = ['GET /{id}' => ['foo' => 'bar']];
        $structure = ["\0" => (new Endpoint('/*'))->withRoute('GET', ['foo' => 'bar'], [])];

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->never())->method('generateInvocation');

        $generate = new GenerateFunction($invoker);

        $generate('', $routes, $structure);
    }

    public function testWithReflectionException()
    {
        $this->expectException(InvalidRouteException::class);
        $this->expectExceptionMessage("Invalid route for 'GET /*'. Can't call info()");

        $routes = ['GET /{id}' => ['controller' => 'info']];
        $structure = ["\0" => (new Endpoint('/*'))->withRoute('GET', ['controller' => 'info'], [])];

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->once())->method('generateInvocation')
            ->willThrowException(new ReflectionException("Can't call info()"));

        $generate = new GenerateFunction($invoker);

        $generate('', $routes, $structure);
    }
}

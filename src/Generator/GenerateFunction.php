<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Generator;

use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\InvalidRouteException;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\InvokerInterface;
use ReflectionException;

/**
 * Generate a function that invokes an action based on the route.
 */
class GenerateFunction extends AbstractGenerate
{
    /**
     * @var InvokerInterface
     */
    protected $invoker;

    /**
     * GenerateScript constructor.
     *
     * @param InvokerInterface $invoker
     */
    public function __construct(InvokerInterface $invoker = null)
    {
        $this->invoker = $invoker ?? new Invoker();
    }

    /**
     * Invoke code generation.
     *
     * @param string $name       Function name
     * @param array  $routes     Ignored
     * @param array  $structure
     * @return string
     */
    public function __invoke(string $name, array $routes, array $structure): string
    {
        $default = $structure["\e"] ?? null;
        unset($structure["\e"]);

        $switchCode = self::indent($this->generateSwitch($structure));
        $defaultCode = self::indent($this->generateDefault($default));

        return <<<CODE
<?php

declare(strict_types=1);

/**
 * This function is generated by SwitchRoute.
 * Do not modify it manually. Any changes will be overwritten.
 */
function {$name}(string \$method, string \$path)
{
    \$segments = \$path === "/" ? [] : explode("/", trim(\$path, "/"));
    \$allowedMethods = [];

$switchCode

$defaultCode
}
CODE;
    }

    /**
     * Generate code for an endpoint
     *
     * @param Endpoint $endpoint
     * @return string
     */
    protected function generateEndpoint(Endpoint $endpoint): string
    {
        $exportValue = function ($var) {
            return var_export($var, true);
        };

        return join("\n", [
            "\$allowedMethods = [" . join(', ', array_map($exportValue, $endpoint->getAllowedMethods())) . "];",
            parent::generateEndpoint($endpoint)
        ]);
    }

    /**
     * Generate routing code for an endpoint.
     *
     * @param string        $key
     * @param array         $route
     * @param array         $vars
     * @param callable|null $genArg
     * @return string
     * @throws InvalidRouteException
     */
    protected function generateRoute(string $key, array $route, array $vars, ?callable $genArg = null): string
    {
        if (!isset($route['include']) && !isset($route['controller']) && !isset($route['action'])) {
            throw new InvalidRouteException("Route for '$key' should specify 'include', 'controller', " .
                "or 'action'");
        }

        if (isset($route['include'])) {
            return "return require '" . addslashes($route['include']) . "';";
        }

        try {
            $genArg = $genArg ?? function (?string $name, ?string $type = null, $default = null) use ($vars) {
                return $this->genArg($vars, $name, $type, $default);
            };

            $invocation = $this->invoker->generateInvocation($route, $genArg);
        } catch (ReflectionException $exception) {
            throw new InvalidRouteException("Invalid route for '$key'. ". $exception->getMessage(), 0, $exception);
        }

        return "return $invocation;";
    }

    /**
     * Generate code for when no route matches.
     *
     * @param Endpoint|null $endpoint
     * @return string
     * @throws InvalidRouteException
     */
    protected function generateDefault(?Endpoint $endpoint): string
    {
        if ($endpoint === null) {
            return $this->invoker->generateDefault();
        }

        $genArg = function ($name, $type = null, $default = null) {
            return "\${$name} ?? " . var_export($default, true);
        };

        return $this->generateRoute('default', $endpoint->getRoutes()[''], [], $genArg);
    }

    /**
     * Generate code for argument using path vars.
     *
     * @param array       $vars
     * @param string|null $name
     * @param string|null $type
     * @param mixed       $default
     * @return string
     */
    protected function genArg(array $vars, ?string $name, ?string $type = null, $default = null): string
    {
        if ($name === null) {
            $fnMap = function ($name, $pos) {
                return sprintf('"%s" => $segments[%d]', addslashes($name), $pos);
            };

            return '[' . join(', ', array_map($fnMap, array_keys($vars), array_values($vars))) . ']';
        }

        return isset($vars[$name]) ? "\$segments[{$vars[$name]}]" : var_export($default, true);
    }
}

<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 compatible middleware that add route attributes to the server request.
 *
 * This file is generated by SwitchRoute.
 * Do not modify it manually. Any changes will be overwritten.
 */
class RouteMiddleware implements MiddlewareInterface
{
    /**
     * Add routing attributes to the server request
     */
    protected function applyRouting(ServerRequestInterface $request): ServerRequestInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $segments = $path === "/" ? [] : explode("/", trim($path, "/"));

        switch ($segments[0] ?? "\0") {
            case "\0":
                $request = $request->withAttribute('route:allowed_methods', ['GET']);
                switch ($method) {
                    case 'GET':
                        return $request
                            ->withAttribute('route:controller', 'info');
                }
                break 1;
            case "users":
                switch ($segments[1] ?? "\0") {
                    case "\0":
                        $request = $request->withAttribute('route:allowed_methods', ['GET', 'POST']);
                        switch ($method) {
                            case 'GET':
                                return $request
                                    ->withAttribute('route:controller', 'user')
                                    ->withAttribute('route:action', 'list');
                            case 'POST':
                                return $request
                                    ->withAttribute('route:controller', 'user')
                                    ->withAttribute('route:action', 'add');
                        }
                        break 2;
                    default:
                        switch ($segments[2] ?? "\0") {
                            case "\0":
                                $request = $request->withAttribute('route:allowed_methods', ['GET', 'POST', 'PUT', 'DELETE']);
                                switch ($method) {
                                    case 'GET':
                                        return $request
                                            ->withAttribute('route:controller', 'user')
                                            ->withAttribute('route:action', 'get')
                                            ->withAttribute('route:{id}', $segments[1]);
                                    case 'POST':
                                    case 'PUT':
                                        return $request
                                            ->withAttribute('route:controller', 'user')
                                            ->withAttribute('route:action', 'update')
                                            ->withAttribute('route:{id}', $segments[1]);
                                    case 'DELETE':
                                        return $request
                                            ->withAttribute('route:controller', 'user')
                                            ->withAttribute('route:action', 'delete')
                                            ->withAttribute('route:{id}', $segments[1]);
                                }
                                break 3;
                            case "photos":
                                switch ($segments[3] ?? "\0") {
                                    case "\0":
                                        $request = $request->withAttribute('route:allowed_methods', ['GET', 'POST']);
                                        switch ($method) {
                                            case 'GET':
                                                return $request
                                                    ->withAttribute('route:action', 'list-photos')
                                                    ->withAttribute('route:{id}', $segments[1]);
                                            case 'POST':
                                                return $request
                                                    ->withAttribute('route:action', 'add-photos')
                                                    ->withAttribute('route:{id}', $segments[1]);
                                        }
                                        break 4;
                                }
                                break 3;
                        }
                        break 2;
                }
                break 1;
            case "export":
                switch ($segments[1] ?? "\0") {
                    case "\0":
                        $request = $request->withAttribute('route:allowed_methods', ['POST']);
                        switch ($method) {
                            case 'POST':
                                return $request
                                    ->withAttribute('route:include', 'scripts/export.php');
                        }
                        break 2;
                }
                break 1;
        }

        return $request
            ->withAttribute('route:action', 'not-found');
    }

    /**
     * Process an incoming server request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($this->applyRouting($request));
    }
}
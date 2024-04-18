<?php

/**
 * @author              : Waris Agung Widodo
 * @Date                : 2017-07-04 15:27:14
 * @Last Modified by    : Drajat Hasan
 * @Last Modified time  : 2024-04-03 14:05:00
 *
 * Copyright (C) 2017  Waris Agung Widodo (ido.alit@gmail.com)
 */


namespace SLiMS\Http;

use AltoRouter;
use Closure;
use SLiMS\Json;
use SLiMS\Http\Response;

class Router extends AltoRouter
{
    const BASE_PATH = 'api';

    /**
     * Route prefix temp property
     */
    private string $prefix = '';

    /**
     * Middleware 
     */
    private string $middleware = '';

    /**
     * Router instance
     */
    private static ?Router $instance = null;

    /**
     * Route collection
     */
    private ?RouteCollection $routeCollection = null;

    private ?Request $request = null;

    private string $requestUrl = '';

    /**
     * Http supported method
     */
    private array $httpMethods = [
        'GET','POST','PATCH',
        'PUT','DELETE','INFO'
    ];

    public function __construct()
    {
        $this->setBasePath(self::BASE_PATH);
        $this->routeCollection = new RouteCollection;
        $this->request = new Request;

        $path = explode('/', $_GET['p']);
        if ($path[0] == $this->basePath) {
            $this->requestUrl = $_GET['p'];
        } else {
            $this->requestUrl = '/';
        }
    }

    /**
     * Tell system for all response as
     * json.
     *
     * @return void
     */
    public static function forceAsJson()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
    }

    /**
     * Matching incoming access with current
     * route data in collection
     *
     * @param string $requestUrl
     * @param string $requestMethod
     * @return array
     */
    public function match($requestUrl = null, $requestMethod = null)
    {
        $params = [];
        $match = false;

        // set Request Url if it isn't passed as parameter
        if($requestUrl === null) {
            $requestUrl = $this->requestUrl;
        }

        // strip base path from request url
        $requestUrl = substr($requestUrl, strlen($this->basePath));

        // Strip query string (?a=b) from Request Url
        if (($strpos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }

        // set Request Method if it isn't passed as a parameter
        if($requestMethod === null) {
            $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        }

        // Route matching 
        $routeFilter = $this->routeCollection->filter(function($handler) use($requestUrl,$params) {
            list($methods, $route, $target, $name) = array_values($handler->toArray());

            if ($route === '*') {
                // * wildcard (matches all)
                $match = true;
            } elseif (isset($route[0]) && $route[0] === '@') {
                // @ regex delimiter
                $pattern = '`' . substr($route, 1) . '`u';
                $match = preg_match($pattern, $requestUrl, $params) === 1;
            } elseif (($position = strpos($route, '[')) === false) {
                // No params in url, do string comparison
                $match = strcmp($requestUrl, $route) === 0;
            } else {
                // Compare longest non-param string with url
                if (strncmp($requestUrl, $route, $position) !== 0) {
                    return;
                }
                $regex = $this->compileRoute($route);
                $match = preg_match($regex, $requestUrl, $params) === 1;
            }

            if ($match) {
                $handler->params($params??[]);
                return true;
            }
        });

        // not mathcing route?
        if ($routeFilter->count() < 1) {
            throw RouterException::routeNotFound($requestUrl);
        }

        // Method matching
        $methodMatch = $routeFilter->filter(function($handler) use($requestMethod) {
            $match = $handler->getMethod() === $requestMethod;

            if (!$match) {
                RouterException::setProperty('at_least_methods', $handler->getMethod());
                RouterException::setProperty('current_method', $requestMethod);
                RouterException::setProperty('current_route', $handler->getPath());
                return false;
            }

            return true;
        });

        if ($methodMatch->count() < 1) {
            throw RouterException::wrongMethod();
        }
        
        // get first route as array
        return $methodMatch->first()->toArray();
    }

    /**
     * Call controller instance or
     * just running callback
     *
     * @param \Closure|array $attributes
     * @param array $params
     * @param string $middleware
     * @return mixed
     */
    public function makeCallable(\Closure|array $attributes, array $params, string $middleware = ''):mixed
    {
        if (!empty($middleware)) {
            if (!class_exists($middleware)) throw new RouterException("Middleware $middleware not found!", 500);
            $middlewareInstance = new $middleware;
        }

        if (is_array($attributes)) {
            list($class, $method) = $attributes;
            $instance = new $class;

            $this->reflectionTarget($instance, $params, $method);

            if (method_exists($instance, $method)) {
                if (isset($middlewareInstance)) {
                    return $middlewareInstance->handle($this->request, function() use($instance, $method, $params) {
                        return call_user_func_array([$instance, $method], $params);
                    });
                }
                return call_user_func_array([$instance, $method], $params);
            }

            unset($instance);
            throw new RouterException("Method {$method} not found in {$class}", 404);
        }

        if (is_callable($attributes)) {
            
            $this->reflectionTarget($attributes, $params);

            if (isset($middlewareInstance)) {
                return $middlewareInstance->handle($this->request, function() use($attributes, $params) {
                    return call_user_func_array($attributes, $params);
                });
            }
            return call_user_func_array($attributes, $params);
        }

        throw new RouterException("Invalid attributes as route format. Attributes must be an array [Controller, Method] or closure", 500);
    }

    private function reflectionTarget($objectOrClosure, array &$params, string $method = '')
    {
        if (!is_callable($objectOrClosure)) {
            $reflectionClass = new \ReflectionClass($objectOrClosure);
            $methodParameter = $reflectionClass->getMethod($method)->getParameters();
        }

        if (is_callable($objectOrClosure)) {
            $reflectionFn = new \ReflectionFunction($objectOrClosure);
            $methodParameter = $reflectionFn->getParameters();
        }

        if (isset($methodParameter[0])) {
            $typeName = $methodParameter[0]->getType()->getName();
            $params = $typeName === Request::class ? array_merge([$this->request], $params) : $params;
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) self::$instance = new Router;
        return self::$instance;
    }

    /**
     * Set route prefix
     *
     * @param string $prefix
     * @param Closure $callback
     * @return void
     */
    public static function prefix(string $prefix, Closure $callback)
    {
        self::getInstance()->prefix = $prefix;
        $callback(self::getInstance());
        self::getInstance()->prefix = '';
    }

    /**
     * Register middleware into router instance
     * and passing it into route collection and
     * reset after route registration is set.
     *
     * @param string $middlewareClass
     * @param Closure $callback
     * @return void
     */
    public static function middleware(string $middlewareClass, Closure $callback)
    {
        self::getInstance()->middleware = $middlewareClass;
        $callback(self::getInstance());
        self::getInstance()->middleware = '';
    }

    /**
     * Register new route
     *
     * @param string $method
     * @param string $routePath
     * @param \Closure|array $target
     * @param string|null $name
     * @return Route
     */
    public function registerRoute(string $method, string $routePath, \Closure|array $target, ?string $name = null): Route
    {
        $this->routeCollection->add($route = new Route($method, $routePath, $target, $name));
        if (!empty($this->middleware)) $route->middleware($this->middleware);
        return $route;
    }

    /**
     * Determine undefine method as route map
     * or not
     *
     * @param string $method
     * @param array $arguments
     * @return void
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $instance = self::getInstance();
        
        if (!empty($instance->prefix)) {
            // dd($arguments);
            $arguments[0] = $instance->prefix . $arguments[0];
        }

        if (in_array(($matchMethod = strtoupper($method)), $instance->httpMethods)) {
            return $instance->registerRoute($matchMethod, ...$arguments);
        }

        throw new RouterException("Http method {$method} is not supported!", 400);
        
    }

    /**
     * Routing incoming request
     * based on available route
     *
     * @return void
     */
    public static function run(?Object $beforeRouting = null)
    {
        if ($beforeRouting !== null) {
            if (!method_exists($beforeRouting, 'handle')) {
                throw new RouterException('Handle method is not available in ' . get_class($beforeRouting), 500);
            }
            $beforeRouting->handle(self::getInstance());
        }

        // match current request url
        $match = self::getInstance()->match();
        $response = self::getInstance()->makeCallable($match['target']??'', $match['params']??[], $match['middleware']??[]);
        
        if ($response instanceof Json) exit($response);
        if (is_object($response)) Response::asJson($response);

        Response::asPlain(is_string($response) ? $response : Json::stringify($response)->withHeader());
        exit;
    }
}
<?php
namespace SLiMS\Http;

use Closure; 

class Route
{
    /**
     * Route name
     */
    private ?string $name = '';

    /**
     * Route method
     */
    private string $method = '';

    /**
     * Route path
     */
    private string $path = '';

    /**
     * Route parameter
     */
    private array $params = [];

    /**
     * Middleware class
     */
    private string $middleware = '';

    /**
     * Route target is a method in
     * a controller or just closure
     */
    private array|Closure $target = [];

    public function __construct(string $method, string $path, array|Closure $target, ?string $name = null)
    {
        $this->method = $method;
        $this->path = $path;
        $this->target = $target;
        $this->name = $name;
    }

    public function name(string $name)
    {
        $this->name = $name;
        return $this;
    }

    public function middleware(string $middlwareClass)
    {
        $this->middleware = $middlwareClass;
        return $this;
    }

    public function params(array $params)
    {
        foreach($params as $key => $value) {
            if(is_numeric($key)) unset($params[$key]);
        }
        $this->params = $params;
        return $this;
    }

    public function toArray()
    {
        return [
            'method' => $this->method, 
            'route' => $this->path, 
            'target' => $this->target, 
            'name' => $this->name,
            'params' => $this->params,
            'middleware' => $this->middleware
        ];
    }

    public function __call(string $method, array $arguments)
    {
        if (substr($method, 0,3) === 'get')
        {
            $property = strtolower(substr($method, 3, strlen($method)));
            if (property_exists($this, $property)) return $this->$property;
            throw new RouterException("Property {$property} not found!", 500);
        }

        throw new RouterException("Method {$method} not found!", 500);
    }
}
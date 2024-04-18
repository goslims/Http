<?php
namespace SLiMS\Http;

class Request
{
    /**
     * Request attributes
     */
    private array $attributes = [
        'file' => [],
        'post' => [],
        'get' => [],
        'header' => [],
        'cookie' => [],
        'request' => [],
        'raw' => []
    ];

    /**
     * List magic function to retrive
     * data frim some attributes
     */
    private array $retrievedByFunction = [
        'query' => 'get',
        'input' => 'post',
        'request' => 'request'
    ];

    public function __construct(array $attributes = [])
    {
        if (empty($attributes)) {
            $this->attributes['file'] = $_FILES;
            $this->attributes['post'] = $_POST;
            $this->attributes['get'] = $_GET;
            $this->attributes['header'] = getallheaders();
            $this->attributes['cookie'] = $_COOKIE;
            $this->attributes['request'] = $_REQUEST;
            $this->attributes['raw'] = file_get_contents('php://input');
        } else {
            $this->attributes = $attributes;
        }
    }

    /**
     * Method to check availbility of
     * some key in attribute
     *
     * @param string $attributeName
     * @param string $key
     * @return boolean
     */
    public function has(string $attributeName, string $key) 
    {
        if (isset($this->attributes[$attributeName])) {
            $exists = isset($this->attributes[$attributeName][$key]);
            $notEmpty = !empty($this->attributes[$attributeName][$key]);
            return $exists && $notEmpty;
        }

        return false;
    }

    /**
     * Determine if attribute is empty or not
     *
     * @param string $attributeName
     * @param string $key
     * @return bool
     */
    public function empty(string $attributeName, string $key = '')
    {
        return (bool)(count($this->attributes[$attributeName]??[]) === 0);
    }

    /**
     * Getter for request attribute
     *
     * @param string $key
     * @param string $default
     * @return array|mixed
     */
    public function request(string $key = '', $default = '')
    {
        $data = $this->attributes['request'][$key]??null;

        return !empty($key) ?  ($data??$default) : $this->attributes['request'];
    }

    /**
     * Method to get raw attribute
     *
     * @return void
     */
    public function raw()
    {
        return $this->attributes['raw']??[];
    }

    /**
     * Decoding raw attribute as json
     *
     * @param string $path
     * @param string $default
     * @return array|mixed
     */
    public function json(string $path = '', $default = '')
    {
        $data = json_decode($this->raw(), true);
        return empty($path) ? $data : getArrayData($data, $path);
    }

    /**
     * Getter for special method
     *
     * @param string $method
     * @param string $key
     * @param [type] $default
     * @return mixed
     */
    private function retrieve(string $method, string $key, $default = null)
    {
        $attributeKey = $this->retrievedByFunction[$method]??[];

        return $this->attributes[$attributeKey][$key]??$default;
    }

    public function __call($method, $arguments)
    {
        // call "has" function
        if (substr($method, 0,3) === 'has') {
            $attributeName = strtolower(substr($method, 3,strlen($method)));
            return $this->has($attributeName, $arguments[0]??'');
        }

        // call from global attributes or retrievedByFunction
        $withPlural = substr($method, -1) === 's';
        $methodWithoutPlural = $withPlural ? substr_replace($method, '', -1) : $method;

        if (isset($this->retrievedByFunction[$methodWithoutPlural])) {
            return $this->retrieve($method, ...$arguments);
        }

        $calledAttribute = $this->attributes[$methodWithoutPlural]??[];
        if ($calledAttribute) {
            return $withPlural ? $calledAttribute : ($calledAttribute[$arguments[0]]??null);
        }
    }
}
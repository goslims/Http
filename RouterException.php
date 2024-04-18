<?php
namespace SLiMS\Http;

use Exception;

class RouterException extends Exception
{
    private static $properties = [
        'at_least_methods' => [],
        'current_method' => '',
        'current_route' => '',
    ];

    public function __construct(string $message, int $httpCode)
    {
        if ($httpCode < 100 || $httpCode > 599) $httpCode = 500;
        parent::__construct($message, $httpCode);
    }
    
    public static function setProperty(string $propertyName, mixed $value) {
        if (isset(self::$properties[$propertyName])) {
            if (is_array(self::$properties[$propertyName])) {
                self::$properties[$propertyName][] = $value;
                return;
            }
            self::$properties[$propertyName] = $value;
        }
    }

    public static function wrongMethod(int $httpCode = 500)
    {
        http_response_code($httpCode);

        $formattedMessage = str_replace([
            '{at_least_methods}','{current_method}',
            '{current_route}',
        ], 
        [
            implode(',', self::$properties['at_least_methods']),
            self::$properties['current_method'],
            self::$properties['current_route']
        ], 
        'Incoming request is {current_method} but this route at least accessed by {at_least_methods}.');

        return new static($formattedMessage, $httpCode);
    }

    public static function routeNotFound(string $path)
    {
        http_response_code(404);
        
        $formattedMessage = str_replace('{current_path}', $path, 'Route {current_path} is not found.');
        
        return new static($formattedMessage, 404);
    }
}
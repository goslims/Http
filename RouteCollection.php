<?php
namespace SLiMS\Http;

use  Ramsey\Collection\AbstractCollection;

class RouteCollection extends AbstractCollection
{
    public function getType(): string
    {
        return Route::class;
    }

    public function hasSameRoute(string $route)
    {
        return count(array_filter($this->data, function($data) use($route) {
            return $data->getRoute() === $route;
        })) > 1;
    }
}
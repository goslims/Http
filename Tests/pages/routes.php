<?php
use SLiMS\Http\Router;

Router::forceAsJson();

Router::get('/', function() {
    return 'Hello Wolrd!';
});

Router::prefix('/v1', function() {
    Router::get('/', function() {
        return 'Prefix /v1';
    });
});

Router::run();
<?php

namespace MyApp\entities;

use MyApp\DIContainer\Container;

class Request
{
    public array $params;
    public string $method;
    public string $uri;
    public array $sessionData;
    public Container $container;
}

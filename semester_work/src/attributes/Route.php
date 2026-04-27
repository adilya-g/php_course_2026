<?php
namespace MyApp\attributes;

use Attribute;

#[Attribute]
class Route
{
    public function __construct(public string $path,
        public array $methods = ['GET'])
    {

    }
}

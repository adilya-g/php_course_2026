<?php

namespace MyApp\middlewares;

use MyApp\entities\Request;

interface IMiddleware
{
    public function handle(Request $request, $next);
}
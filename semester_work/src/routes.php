<?php

return [
    [
        'method' => 'GET',
        'pattern' => '/auth',
        'controller' => 'AuthController',
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'pattern' => '/auth/success',
        'controller' => 'AuthController',
        'action' => 'success'
    ],
    [
        'method' => 'GET',
        'pattern' => '/auth/logout',
        'controller' => 'AuthController',
        'action' => 'logout'
    ]
];
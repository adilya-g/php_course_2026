<?php

return [
    [
        'method' => 'GET',
        'pattern' => '/',
        'controller' => 'HomeController',
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'pattern' => '/auth',
        'controller' => 'AuthController',
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'pattern' => '/auth/gmail',
        'controller' => 'AuthController',
        'action' => 'fetchAndSaveGmailData'
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
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/',
        'controller' => 'HomeController',
        'action'     => 'index'
    ],
    // Главная
    [
        'method'     => 'GET',
        'pattern'    => '/',
        'controller' => 'StaticPageController',
        'action'     => 'show'
    ],
    // Статические страницы
    [
        'method'     => 'GET',
        'pattern'    => '/about',
        'controller' => 'StaticPageController',
        'action'     => 'show'
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/contacts',
        'controller' => 'StaticPageController',
        'action'     => 'show'
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/privacy',
        'controller' => 'StaticPageController',
        'action'     => 'show'
    ],
    // Отправка формы
    [
        'method'     => 'POST',
        'pattern'    => '/contacts/send',
        'controller' => 'StaticPageController',
        'action'     => 'sendContactForm'
    ],
];
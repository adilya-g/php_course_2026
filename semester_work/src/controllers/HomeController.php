<?php

namespace MyApp\Controllers;
require_once __DIR__ . '/../../vendor/autoload.php';

use MyApp\attributes\Route;
use MyApp\database\database;
use MyApp\Controllers;
use PDO;
use PDOException;
use MyApp\Logging;

class HomeController extends AbstractController
{
    #[Route("/", ['GET'])]
    public function index(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->render('home.html');
    }
}


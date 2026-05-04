<?php

namespace MyApp\Controllers;

use MyApp\attributes\Route;
use MyApp\database\Database;
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

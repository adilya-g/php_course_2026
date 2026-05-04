<?php

namespace MyApp\controllers;

use MyApp\attributes\Route;
use MyApp\Controllers\AbstractController;
use MyApp\Logging\FileLogger;
use MyApp\Services\AuthService;
use MyApp\Services\GmailService;

class EmailController extends AbstractController
{
    private AuthService $authService;
    private GmailService $gmailService;
    private FileLogger $fileLogger;

    public function __construct(AuthService $authService, GmailService $gmailService, FileLogger $fileLogger)
    {
        $this->authService = $authService;
        $this->gmailService = $gmailService;
        $this->fileLogger = $fileLogger;
    }
    #[Route("/home/mails", ["GET"])]
    public function getMails()
    {
        $this->fileLogger->info("Getting emails");
        $mails = $this->gmailService->getEmailsFromGmail();
        $jsonResponse = json_encode($mails);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Content-Length: ' . strlen($jsonResponse));
        echo $jsonResponse;
    }
}

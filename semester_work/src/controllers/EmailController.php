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
    #[Route("/api/emails", ["GET"])]
    public function getMails()
    {
        $this->fileLogger->info("Getting emails");
        $userId = $this->gmailService->resolveUser()->userId;
        $mails = $this->gmailService->getEmailsFromRepository($userId);
        $jsonResponse = json_encode($mails);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Content-Length: ' . strlen($jsonResponse));
        echo $jsonResponse;
    }

    #[Route("/api/emails/sync", ["GET"])]
    public function syncEmails()
    {
        $this->fileLogger->info("Syncing emails");
        $newEmails = $this->gmailService->syncMails();
        if(empty($newEmails)) {
            $this->fileLogger->info("No new emails");
        }
        $jsonResponse = json_encode($newEmails);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Content-Length: ' . strlen($jsonResponse));
        echo $jsonResponse;
    }

    public function removeEmail()
    {
        $this->fileLogger->info("Removing email");
    }
}

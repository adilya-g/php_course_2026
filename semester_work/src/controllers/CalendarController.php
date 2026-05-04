<?php

namespace MyApp\controllers;

use MyApp\attributes\Route;
use MyApp\Controllers\AbstractController;
use MyApp\Logging\FileLogger;
use MyApp\Services\AuthService;
use MyApp\Services\CalendarService;
use MyApp\Services\GmailService;

class CalendarController extends AbstractController
{
    private AuthService $authService;
    private GmailService $gmailService;
    private FileLogger $fileLogger;
    private CalendarService $calendarService;

    public function __construct(
        AuthService $authService,
        GmailService $gmailService,
        FileLogger $fileLogger,
        CalendarService $calendarService
    ) {
        $this->authService = $authService;
        $this->gmailService = $gmailService;
        $this->fileLogger = $fileLogger;
        $this->calendarService = $calendarService;
    }

    #[Route("/home/events", ["GET"])]
    public function getCalendar()
    {
        $events = $this->calendarService->getGoogleEvents();
        $jsonResponse = json_encode($events);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Content-Length: ' . strlen($jsonResponse));
        echo $jsonResponse;
    }
}

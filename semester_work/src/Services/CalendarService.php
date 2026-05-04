<?php

namespace MyApp\Services;

use Google\Client;
use Google_Service_Calendar;
use Google_Service_Exception;
use Google_Service_Gmail;
use Google_Service_Oauth2;
use MyApp\attributes\Route;
use MyApp\Logging\FileLogger;
use MyApp\repositories\interfaces\IMailRepository;
use MyApp\repositories\interfaces\ITokenRepository;
use MyApp\repositories\interfaces\IUserRepository;

class CalendarService
{
    private ITokenRepository $tokenRepository;
    private IUserRepository $userRepository;
    private IMailRepository $mailRepository;
    private Client $googleClient;
    private FileLogger $fileLogger;

    public function __construct(
        ITokenRepository $tokenRepository,
        IUserRepository $userRepository,
        IMailRepository $mailRepository,
        FileLogger $fileLogger,
        AuthService $authService,
    ) {
        $this->tokenRepository = $tokenRepository;
        $this->userRepository = $userRepository;
        $this->mailRepository = $mailRepository;
        $this->fileLogger = $fileLogger;
        $this->googleClient = $authService->googleClient;
    }

    public function getGoogleEvents()
    {
        try {
            $events = [];

            $calendarService = new Google_Service_Calendar($this->googleClient);

            $calendarList = $calendarService->calendarList->listCalendarList();

            if ($calendarList && $calendarList->getItems()) {
                foreach ($calendarList->getItems() as $calendar) {
                    $calendarId = $calendar->getId();

                    $optParams = [
                        'maxResults' => 50,
                        'orderBy' => 'startTime',
                        'singleEvents' => true,
                        'timeMin' => date('c'),
                    ];
                    $eventsResult = $calendarService->events->listEvents($calendarId, $optParams);

                    if ($eventsResult && $eventsResult->getItems()) {
                        foreach ($eventsResult->getItems() as $event) {
                            $events[] = [
                                'summary' => $event->getSummary(),
                                'start'   => $event->getStart()->getDateTime() ?? $event->getStart()->getDate(),
                                'end'     => $event->getEnd()->getDateTime() ?? $event->getEnd()->getDate(),
                                'location' => $event->getLocation(),
                                'description' => $event->getDescription(),
                                'calendarName' => $calendar->getSummary(),
                                'id' => $event->getId(),
                            ];
                        }
                    }
                }
            } else {
                $this->fileLogger->warning("getGoogleEvents: No calendars found for this user.");
            }
            return $events;
        } catch (\Google_Service_Exception $e) {
            $this->fileLogger->error("getGoogleEvents (Service Error): " . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            $this->fileLogger->error("getGoogleEvents (General Error): " . $e->getMessage());
            return [];
        }
    }
}

<?php

namespace MyApp\Services;

use Google_Exception;
use Google_Service_Exception;
use Google_Service_Gmail;
use Google_Service_Oauth2;
use MyApp\Logging\FileLogger;
use MyApp\Logging\LoggerFactory;
use MyApp\repositories\interfaces\IMailRepository;
use MyApp\repositories\interfaces\ITokenRepository;
use MyApp\repositories\interfaces\IUserRepository;
use MyApp\Entities\Mail;
use Google\Client;

class GmailService
{
    private ITokenRepository $tokenRepository;
    private IUserRepository $userRepository;
    private IMailRepository $mailRepository;
    private Client $googleClient;
    private FileLogger $fileLogger;
    private Google_Service_Gmail $googleGmailService;

    function __construct(ITokenRepository $tokenRepository,
                         IUserRepository $userRepository,
                         IMailRepository $mailRepository,
                         FileLogger $fileLogger,
                         AuthService $authService)
    {
        $this->tokenRepository = $tokenRepository;
        $this->userRepository = $userRepository;
        $this->mailRepository = $mailRepository;
        $this->fileLogger = $fileLogger;
        $this->googleClient = $authService->googleClient;
    }

    public function getEmailsFromRepository(int $userId): array
    {
        $mails = $this->mailRepository->getMails($userId);
        return $mails;
    }

    public function saveNewEmails(int $userId, array $mails): void
    {
        $currentMail = null;
        foreach ($mails as $mail)
        {
            $currentMail = $this->mailRepository->saveMail($mail);
            if (is_null($currentMail))
            {
                $this->fileLogger->error("Failed to save mail: " . $mail->messageId);
            }
        }
    }

    public function getEmailsFromGmail(): array
    {
        try {
            $service = new Google_Service_Gmail($this->googleClient);
            $oauth2 = new Google_Service_Oauth2($this->googleClient);
            $userInfo = $oauth2->userinfo->get();
            $email = $userInfo->email;
            $this->fileLogger->info("Getting email from gmail: " . $email);
            // Получаем пользователя из БД
            $user = $this->userRepository->getUserByEmail($email);
            if (is_null($user)) {
                $this->fileLogger->error("Failed to find user: " . $email);
                return [];
            }

            $messages = [];
            $gmailUser = 'me';

            // Получаем список писем
            $messagesList = $service->users_messages->listUsersMessages($gmailUser, ['maxResults' => 10]);

            if (!$messagesList || !$messagesList->getMessages()) {
                $this->fileLogger->info("No messages found for user: " . $email);
                return [];
            }

            // Получаем полные данные каждого письма
            foreach ($messagesList->getMessages() as $msg) {
                try {
                    $fullMessage = $service->users_messages->get($gmailUser, $msg->getId());
                    $messages[] = $fullMessage;
                } catch (Exception $e) {
                    $this->fileLogger->error("Failed to fetch message {$msg->getId()}: " . $e->getMessage());
                }
            }

            // Сохраняем письма в БД
            //$this->saveNewEmails($user->userId, $messages);

            return $messages;

        } catch (Google_Service_Exception $e) {
            $this->fileLogger->error("Gmail API service error: " . $e->getMessage());
            return [];
        } catch (Google_Exception $e) {
            $this->fileLogger->error("Google client error: " . $e->getMessage());
            return [];
        } catch (Exception $e) {
            $this->fileLogger->error("Unexpected error: " . $e->getMessage());
            return [];
        }
    }
}
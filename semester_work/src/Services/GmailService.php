<?php

namespace MyApp\Services;

use Exception;
use Google_Exception;
use Google_Service_Exception;
use Google_Service_Gmail;
use Google_Service_Oauth2;
use MyApp\entities\Sender;
use MyApp\entities\User;
use MyApp\Logging\FileLogger;
use MyApp\Logging\LoggerFactory;
use MyApp\repositories\interfaces\ILabelRepository;
use MyApp\repositories\interfaces\IMailRepository;
use MyApp\repositories\interfaces\ISenderRepository;
use MyApp\repositories\interfaces\ITokenRepository;
use MyApp\repositories\interfaces\IUserRepository;
use MyApp\Entities\Mail;
use Google\Client;

class GmailService
{
    private ITokenRepository $tokenRepository;
    private IUserRepository $userRepository;
    private IMailRepository $mailRepository;
    private ISenderRepository $senderRepository;
    private ILabelRepository $labelRepository;
    private Client $googleClient;
    private FileLogger $fileLogger;

    public function __construct(
        ITokenRepository $tokenRepository,
        IUserRepository $userRepository,
        IMailRepository $mailRepository,
        ISenderRepository $senderRepository,
        ILabelRepository $labelRepository,
        FileLogger $fileLogger,
        AuthService $authService,
    ) {
        $this->tokenRepository = $tokenRepository;
        $this->userRepository = $userRepository;
        $this->mailRepository = $mailRepository;
        $this->senderRepository = $senderRepository;
        $this->labelRepository = $labelRepository;
        $this->fileLogger = $fileLogger;
        $this->googleClient = $authService->googleClient;
    }

    public function getEmailsFromRepository(int $userId): array
    {
        $mails = $this->mailRepository->getMails($userId);
        return $mails;
    }

    public function syncMails(): array
    {
        try {

            $user = $this->resolveUser();

            if (!$user) {
                return [];
            }

            $historyRecords = $this->fetchHistory($user);

            if (empty($historyRecords)) {
                return [];
            }

            $result = [];

            foreach ($historyRecords as $record) {

                $messages = $this->extractMessages($record);

                if (empty($messages)) {
                    continue;
                }

                foreach ($messages as $message) {

                    $mail = $this->processMessage($user, $message);

                    if ($mail) {
                        $result[] = $mail;
                    }
                }

                $this->updateHistory($user, $record);
            }

            return $result;

        } catch (\Exception $e) {

            $this->fileLogger->error($e->getMessage());

            return [];
        }
    }

    public function resolveUser(): ?User
    {
        $oauth2 = new Google_Service_Oauth2($this->googleClient);

        $userInfo = $oauth2->userinfo->get();

        $email = $userInfo->email;

        $this->fileLogger->info("Getting email from gmail: " . $email);

        return $this->userRepository->getUserByEmail($email);
    }

    private function fetchHistory(User $user): array
    {
        try
        {
            $service = new Google_Service_Gmail($this->googleClient);

            $lastHistoryId = $this->mailRepository
                ->getLastHistoryId($user->id);

            if (!$lastHistoryId) {
                $this->fileLogger->error("HistoryId not found");
                return [];
            }

            $history = $service->users_history->listUsersHistory(
                'me',
                [
                    'startHistoryId' => $lastHistoryId,
                    'historyTypes' => ['messageAdded']
                ]
            );

            return $history->getHistory() ?? [];
        }
        catch (Google_Service_Exception $e)
        {
            $this->fileLogger->error($e->getMessage());
            return [];
        }
    }

    private function extractMessages($record): array
    {
        return $record->getMessagesAdded() ?? [];
    }

    private function processMessage(User $user, $addedMessage): ?Mail
    {
        $service = new Google_Service_Gmail($this->googleClient);

        $message = $addedMessage->getMessage();

        $fullMessage = $service->users_messages->get(
            'me',
            $message->getId(),
            ['format' => 'full']
        );

        $meta = $this->parseMessage($fullMessage);

        $senderId = $this->resolveSender($meta['from']);

        $mail = $this->buildMail($user, $meta, $senderId);

        $saved = $this->mailRepository->saveMail($mail);

        $this->syncLabels($saved->id, $fullMessage);

        return $saved;
    }

    private function parseMessage($fullMessage): array
    {
        $headers = $fullMessage->getPayload()->getHeaders();

        $subject = null;
        $from = null;

        foreach ($headers as $header) {

            if ($header->getName() === 'Subject') {
                $subject = $header->getValue();
            }

            if ($header->getName() === 'From') {
                $from = $header->getValue();
            }
        }

        $internalDate = $fullMessage->getInternalDate();

        return [
            'messageId' => $fullMessage->getId(),
            'threadId' => $fullMessage->getThreadId(),
            'historyId' => (string)$fullMessage->getHistoryId(),
            'snippet' => $fullMessage->getSnippet(),
            'subject' => $subject ?? '(No subject)',
            'from' => $from,
            'receivedAt' => (new \DateTimeImmutable())
                ->setTimestamp($internalDate / 1000),
            'createdAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ];
    }

    private function resolveSender(string $from): Sender
    {
        return $this->senderRepository->getOrCreate($from);
    }

    private function buildMail(User $user, array $meta, int $senderId): Mail
    {
        $mail = new Mail();

        $mail->userId = $user->id;
        $mail->messageId = $meta['messageId'];
        $mail->subject = $meta['subject'];
        $mail->senderId = $senderId;
        $mail->recipient = $user->email;
        $mail->snippet = $meta['snippet'];
        $mail->createdAt = $meta['createdAt'];
        $mail->receivedAt = $meta['receivedAt'];
        $mail->historyId = $meta['historyId'];
        $mail->threadId = $meta['threadId'];
        $mail->link = $this->buildLink($meta['threadId']);
        $mail->importance = 2;

        return $mail;
    }

    private function buildLink(string $threadId): string
    {
        return "https://mail.google.com/mail/u/0/#inbox/" . $threadId;
    }

    private function syncLabels(int $emailId, $fullMessage): void
    {
        $labels = $fullMessage->getLabelIds() ?? [];

        foreach ($labels as $label) {

            $this->labelRepository->getOrCreate($label);

            $this->labelRepository->attachLabelsToMessage($emailId, [$label]
            );
        }
    }

    public function saveNewEmails(int $userId, array $mails): void
    {
        $currentMail = null;
        foreach ($mails as $mail) {
            $currentMail = $this->mailRepository->saveMail($mail);
            if (is_null($currentMail)) {
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

            $user = $this->userRepository->getUserByEmail($email);
            if (is_null($user)) {
                $this->fileLogger->error("Failed to find user: " . $email);
                return [];
            }

            $messages = [];
            $gmailUser = 'me';

            $messagesList = $service->users_messages->listUsersMessages($gmailUser, ['maxResults' => 10]);

            if (!$messagesList || !$messagesList->getMessages()) {
                $this->fileLogger->info("No messages found for user: " . $email);
                return [];
            }

            foreach ($messagesList->getMessages() as $msg) {
                try {
                    $fullMessage = $service->users_messages->get($gmailUser, $msg->getId());
                    $messages[] = $fullMessage;
                } catch (Exception $e) {
                    $this->fileLogger->error("Failed to fetch message {$msg->getId()}: " . $e->getMessage());
                }
            }

            $this->saveNewEmails($user->userId, $messages);

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

    private function updateHistory(User $user, $record): void
    {
        try {

            $historyId = $record->getId();

            if (!$historyId) {

                $this->fileLogger->error("History id not found");

                return;
            }

            $saved = $this->mailRepository->saveLastHistoryId(
                $user->id, (string)$historyId
            );

            if (!$saved) {

                $this->fileLogger->error("Failed to update history id");
            }

        } catch (\Exception $e) {

            $this->fileLogger->error($e->getMessage());
        }
    }
}

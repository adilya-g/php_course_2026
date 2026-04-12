<?php

namespace MyApp\Controllers;

use MyApp\AbstractController;
use Google\Client;
use Google_Service_Gmail;

class AuthController extends AbstractController
{
    private Client $googleClient;
    private string $redirectUri;
    
    public function __construct()
    {
        $this->googleClient = new Client();
        $this->redirectUri = $this->getRedirectUri();
        
        $this->configureGoogleClient();
    }
    
    private function configureGoogleClient(): void
    {
        $configPath = __DIR__ . '/../../config/client_secret.json';
        
        if (!file_exists($configPath)) {
            throw new \Exception('Google client secret file not found');
        }
        
        $this->googleClient->setAuthConfig($configPath);
        $this->googleClient->addScope(Google_Service_Gmail::GMAIL_READONLY);
        $this->googleClient->setRedirectUri($this->redirectUri);
        $this->googleClient->setAccessType('offline');
        $this->googleClient->setPrompt('consent');
    }
    
    private function getRedirectUri(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = '/public/index.php';
        
        return $protocol . $host . $basePath;
    }
    
    public function index(array $params = []): void
    {
        if (!isset($params['code']) && !isset($_GET['code'])) {
            $this->redirectToGoogleAuth();
            return;
        }
        
        $code = $params['code'] ?? $_GET['code'] ?? null;
        
        if ($code) {
            $this->authenticateWithCode($code);
            return;
        }
        
        $this->checkAndUseToken();
    }
    
    private function redirectToGoogleAuth(): void
    {
        $authUrl = $this->googleClient->createAuthUrl();
        header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
        exit;
    }
    
    private function authenticateWithCode(string $code): void
    {
        try {
            $this->googleClient->authenticate($code);
            $accessToken = $this->googleClient->getAccessToken();
            
            $_SESSION['google_access_token'] = $accessToken;
            
            $this->saveTokenToDatabase($accessToken);
            
            $this->redirect('/auth/success');
            
        } catch (\Exception $e) {
            $this->handleError('Authentication failed: ' . $e->getMessage());
        }
    }
    
    private function checkAndUseToken(): void
    {
        if (isset($_SESSION['google_access_token']) && $_SESSION['google_access_token']) {
            $this->googleClient->setAccessToken($_SESSION['google_access_token']);
            
            if ($this->googleClient->isAccessTokenExpired()) {
                if ($this->googleClient->getRefreshToken()) {
                    $this->refreshAccessToken();
                } else {
                    $this->clearAuthAndRedirect();
                }
            } else {
                $this->showGmailData();
            }
        } else {
            $this->redirectToGoogleAuth();
        }
    }
    
    private function refreshAccessToken(): void
    {
        try {
            $this->googleClient->fetchAccessTokenWithRefreshToken(
                $this->googleClient->getRefreshToken()
            );
            $_SESSION['google_access_token'] = $this->googleClient->getAccessToken();
            
            $this->updateTokenInDatabase($_SESSION['google_access_token']);
            
            $this->redirect('/auth');
            
        } catch (\Exception $e) {
            $this->handleError('Token refresh failed: ' . $e->getMessage());
        }
    }
    
    private function clearAuthAndRedirect(): void
    {
        unset($_SESSION['google_access_token']);
        $this->redirect('/auth');
    }
    
    private function showGmailData(): void
    {
        try {
            $service = new Google_Service_Gmail($this->googleClient);
            $user = 'me';
            
            $messages = $service->users_messages->listUsersMessages($user, ['maxResults' => 10]);
            
            $emails = [];
            foreach ($messages->getMessages() as $message) {
                $fullMessage = $service->users_messages->get($user, $message->getId());
                $emails[] = $this->parseEmailData($fullMessage);
            }
            
            $this->render('auth/gmail', [
                'title' => 'Ваши письма Gmail',
                'emails' => $emails,
                'totalCount' => count($emails)
            ]);
            
        } catch (\Exception $e) {
            $this->handleError('Failed to fetch Gmail data: ' . $e->getMessage());
        }
    }
    
    private function parseEmailData($message): array
    {
        $data = [
            'id' => $message->getId(),
            'subject' => '',
            'from' => '',
            'date' => '',
            'snippet' => $message->getSnippet()
        ];
        
        foreach ($message->getPayload()->getHeaders() as $header) {
            switch ($header->getName()) {
                case 'Subject':
                    $data['subject'] = $header->getValue();
                    break;
                case 'From':
                    $data['from'] = $header->getValue();
                    break;
                case 'Date':
                    $data['date'] = $header->getValue();
                    break;
            }
        }
        
        return $data;
    }
    
    public function success(array $params = []): void
    {
        $this->render('auth/success', [
            'title' => 'Авторизация успешна',
            'message' => 'Вы успешно авторизовались через Google'
        ]);
    }
    
    public function logout(array $params = []): void
    {
        unset($_SESSION['google_access_token']);
        
        if (isset($_SESSION['google_access_token'])) {
            try {
                $this->googleClient->revokeToken();
            } catch (\Exception $e) {
            }
        }
        
        $this->redirect('/');
    }
    
    private function saveTokenToDatabase(array $token): void
    {
    }
    
    private function updateTokenInDatabase(array $token): void
    {
        $this->saveTokenToDatabase($token);
    }
    
    private function handleError(string $message): void
    {
        error_log($message);
        
        $this->render('error', [
            'title' => 'Ошибка авторизации',
            'message' => $message
        ]);
    }
}
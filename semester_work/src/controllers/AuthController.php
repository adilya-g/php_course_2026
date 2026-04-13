<?php

namespace MyApp\Controllers;

require_once 'vendor/autoload.php';

use MyApp\Controllers\AbstractController;
use MyApp\Exceptions\AuthenticationException;
use MyApp\Exceptions\ApiException;
use MyApp\Logging\LoggerFactory;
use Google\Client;
use Google_Service_Gmail;
use Google_Service_Exception;
use Exception;

class AuthController extends AbstractController
{
    private Client $googleClient;
    private string $redirectUri;
    
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->googleClient = new Client();
        $this->redirectUri = $this->getRedirectUri();   
        $this->configureGoogleClient();
    }
    
    private function configureGoogleClient(): void
    {
        $configPath = __DIR__ . ($_ENV['GOOGLE_CLIENT_SECRET_PATH'] ?? '/../../client_secret.json');
        
        if (!file_exists($configPath)) {
            LoggerFactory::getLogger()->critical('Google client secret file not found', ['path' => $configPath]);
            throw new AuthenticationException('Отсутствует файл конфигурации Google OAuth.');
        }
        
        try {
            $this->googleClient->setAuthConfig($configPath);
            
            $verifySSL = filter_var($_ENV['GUZZLE_VERIFY_SSL'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $this->googleClient->setHttpClient(new \GuzzleHttp\Client(['verify' => $verifySSL]));
            
            $this->googleClient->addScope(Google_Service_Gmail::GMAIL_READONLY);
            $this->googleClient->setRedirectUri($this->redirectUri);
            $this->googleClient->setAccessType('offline');
            $this->googleClient->setPrompt('consent');
        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('Google client configuration failed', ['error' => $e->getMessage()]);
            throw new AuthenticationException('Ошибка настройки Google клиента.', 0, $e);
        }
    }
    
    private function getRedirectUri(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . $host . '/auth';
    }
    
    public function ensureValidToken(): bool
    {
        if (!isset($_SESSION['google_access_token']) || empty($_SESSION['google_access_token'])) {
            LoggerFactory::getLogger()->warning('ensureValidToken: no token in session');
            return false;
        }
            
        $this->googleClient->setAccessToken($_SESSION['google_access_token']);
        
        if ($this->googleClient->isAccessTokenExpired()) {
            $refreshToken = $this->googleClient->getRefreshToken();
            if ($refreshToken) {
                try {
                    $this->googleClient->fetchAccessTokenWithRefreshToken($refreshToken);
                    $newToken = $this->googleClient->getAccessToken();
                    if (!empty($newToken)) {
                        $_SESSION['google_access_token'] = $newToken;
                        $this->updateTokenInDatabase($newToken);
                        LoggerFactory::getLogger()->info('Token refreshed successfully');
                        return true;
                    } else {
                        LoggerFactory::getLogger()->error('Failed to obtain new token during refresh');
                        $this->clearAuth();
                        return false;
                    }
                } catch (Exception $e) {
                    LoggerFactory::getLogger()->error('Token refresh error', ['error' => $e->getMessage()]);
                    $this->clearAuth();
                    return false;
                }
            } else {
                LoggerFactory::getLogger()->warning('No refresh token available, token expired');
                $this->clearAuth();
                return false;
            }
        }
        
        return true;
    }
    
    private function clearAuth(): void
    {
        unset($_SESSION['google_access_token']);
    }
    
    public function index(array $params = []): void
    {
        $code = $params['code'] ?? $_GET['code'] ?? null;
        if ($code) {
            $this->authenticateWithCode($code);
            return;
        }
        
        if ($this->ensureValidToken()) {
            $this->fetchAndSaveGmailData();
        } else {
            $this->redirectToGoogleAuth();
        }
    }
    
    private function authenticateWithCode(string $code): void
    {
        try {
            $this->googleClient->authenticate($code);
            $accessToken = $this->googleClient->getAccessToken();
            if (empty($accessToken)) {
                throw new AuthenticationException('Empty token after authentication');
            }
            $_SESSION['google_access_token'] = $accessToken;
            LoggerFactory::getLogger()->info('Token obtained and saved to session');
            
            header('Location: ' . $this->redirectUri);
            exit;
            
        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('Authentication with code failed', ['error' => $e->getMessage()]);
            throw new AuthenticationException('Ошибка аутентификации через Google.', 0, $e);
        }
    }
    
    private function redirectToGoogleAuth(): void
    {
        $authUrl = $this->googleClient->createAuthUrl();
        header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
        exit;
    }
    
    private function clearAuthAndRedirect(): void
    {
        unset($_SESSION['google_access_token']);
        $this->redirect('/auth');
    }
    
    public function fetchAndSaveGmailData(): void
    {
        try {
            if ($this->googleClient->isAccessTokenExpired()) {
                if ($this->googleClient->getRefreshToken()) {
                    $this->googleClient->fetchAccessTokenWithRefreshToken(
                        $this->googleClient->getRefreshToken()
                    );
                    $_SESSION['google_access_token'] = $this->googleClient->getAccessToken();
                } else {
                    $this->clearAuthAndRedirect();
                    return;
                }
            }

            $service = new Google_Service_Gmail($this->googleClient);
            $user = 'me';
            
            $messages = $service->users_messages->listUsersMessages($user, ['maxResults' => 10]);
            
            $emails = [];
            foreach ($messages->getMessages() as $message) {
                $fullMessage = $service->users_messages->get($user, $message->getId());
                $emails[] = $this->parseEmailData($fullMessage);
            }
            
            $this->saveEmailsToJson($emails);
            
            $this->redirect('/');
            
        } catch (Google_Service_Exception $e) {
            $error = json_decode($e->getMessage(), true);
            LoggerFactory::getLogger()->error('Google API error', ['error' => $error]);
            throw ApiException::fromGoogleError($error, $e);
        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('Failed to fetch Gmail data', ['error' => $e->getMessage()]);
            throw new ApiException('Не удалось загрузить письма.', 0, $e);
        }
    }

    private function saveEmailsToJson(array $emails): void
    {
        $storageDir = __DIR__ . '/../../storage/emails';
        
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        
        $filename = $storageDir . '/emails_' . date('Y-m-d_His') . '.json';
        file_put_contents($filename, json_encode($emails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
    
    public function logout(array $params = []): void
    {
        unset($_SESSION['google_access_token']);
        
        if (isset($_SESSION['google_access_token'])) {
            try {
                $this->googleClient->revokeToken();
            } catch (Exception $e) {
                LoggerFactory::getLogger()->warning('Token revocation failed', ['error' => $e->getMessage()]);
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
}
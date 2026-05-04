<?php

namespace MyApp\Services;

use Google\Client;
use Google\Service\Oauth2 as Google_Service_Oauth2;
use Google\Service\Gmail as Google_Service_Gmail;
use Google\Service\Calendar as Google_Service_Calendar;
use MyApp\Exceptions\AuthenticationException;
use MyApp\Logging\LoggerFactory;
use Exception;
use MyApp\repositories\interfaces\ITokenRepository;
use MyApp\repositories\interfaces\IUserRepository;

class AuthService
{
    public Client $googleClient;
    private string $redirectUri;
    private ITokenRepository $tokenRepository;
    private IUserRepository $userRepository;

    public function __construct(
        ITokenRepository $tokenRepository,
        IUserRepository $userRepository,
    ) {
        $this->googleClient = new Client();
        $this->redirectUri = $this->getRedirectUri();
        $this->configureGoogleClient();
        $this->tokenRepository = $tokenRepository;
        $this->userRepository = $userRepository;
    }

    public function handleAuthFlow(?string $authCode): void
    {
        if ($authCode !== null) {
            $this->exchangeCode($authCode);
            return;
        }

        if ($this->hasAccessToken()) {
            $this->validateToken();
            return;
        }

        if ($this->hasRefreshToken()) {
            $this->refreshAccessToken();
            return;
        }

        $this->authorize();
    }

    public function getUserData(): array
    {
        $this->validateToken();

        try {
            $oauth2 = new Google_Service_Oauth2($this->googleClient);
            $userInfo = $oauth2->userinfo->get();

            $accessToken = $this->googleClient->getAccessToken();
            $accessTokenString = is_array($accessToken)
                ? ($accessToken['access_token'] ?? '')
                : $accessToken;

            return [
                'email' => $userInfo->getEmail(),
                'access_token' => $accessTokenString,
            ];
        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('User data fetch failed', ['error' => $e->getMessage()]);
            throw new AuthenticationException('Не удалось получить данные пользователя.', 0, $e);
        }
    }

    public function exchangeCode(string $authCode): void
    {
        try {
            $token = $this->googleClient->fetchAccessTokenWithAuthCode($authCode);

            if (!empty($token['error'])) {
                throw new AuthenticationException($token['error']);
            }

            $this->googleClient->setAccessToken($token);
            $_SESSION['access_token'] = $token;

            if (!empty($token['refresh_token'])) {
                $_SESSION['refresh_token'] = $token['refresh_token'];
            }
        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('Code exchange failed', ['error' => $e->getMessage()]);
            throw new AuthenticationException('Ошибка обмена кода.', 0, $e);
        }
    }

    public function validateToken(): void
    {
        if (!$this->hasAccessToken()) {
            $this->authorize();
        }

        if ($this->googleClient->isAccessTokenExpired()) {
            if ($this->hasRefreshToken()) {
                $this->refreshAccessToken();
                return;
            }

            $this->authorize();
        }
    }

    private function refreshAccessToken(): void
    {
        try {
            $token = $this->googleClient->fetchAccessTokenWithRefreshToken($_SESSION['refresh_token']);

            if (!empty($token['error'])) {
                throw new AuthenticationException($token['error']);
            }

            $this->googleClient->setAccessToken($token);
            $_SESSION['access_token'] = $token;

            if (!empty($token['refresh_token'])) {
                $_SESSION['refresh_token'] = $token['refresh_token'];
            }
        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('Token refresh failed', ['error' => $e->getMessage()]);
            throw new AuthenticationException('Ошибка обновления токена.', 0, $e);
        }
    }

    public function authorize(): void
    {
        header('Location: ' . $this->googleClient->createAuthUrl());
        exit;
    }

    private function hasAccessToken(): bool
    {
        if (!isset($_SESSION['access_token'])) {
            return false;
        }

        $this->googleClient->setAccessToken($_SESSION['access_token']);
        return true;
    }

    private function hasRefreshToken(): bool
    {
        return isset($_SESSION['refresh_token']);
    }

    private function getRedirectUri(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . '/';
    }

    private function configureGoogleClient(): void
    {
        $configPath = __DIR__ . ($_ENV['GOOGLE_CLIENT_SECRET_PATH'] ?? '/../../client_secret.json');

        if (!file_exists($configPath)) {
            throw new AuthenticationException('Нет конфигурации Google.');
        }

        $this->googleClient->setAuthConfig($configPath);

        $verifySSL = filter_var($_ENV['GUZZLE_VERIFY_SSL'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->googleClient->setHttpClient(new \GuzzleHttp\Client(['verify' => $verifySSL]));

        $this->googleClient->addScope(Google_Service_Gmail::GMAIL_READONLY);
        $this->googleClient->addScope(Google_Service_Calendar::CALENDAR_READONLY);
        $this->googleClient->addScope('email');
        $this->googleClient->addScope('profile');

        $this->googleClient->setRedirectUri($this->redirectUri);
        $this->googleClient->setAccessType('offline');
        $this->googleClient->setPrompt('consent');
    }
}

<?php
namespace MyApp\Controllers;
require_once __DIR__ . '/../../vendor/autoload.php';


use Exception;
use Google\Client;
use Google_Service_Exception;
use Google_Service_Gmail;
use MyApp\database\database;
use MyApp\Exceptions\AppException;
use MyApp\Exceptions\AuthenticationException;
use MyApp\Logging\LoggerFactory;
use PDO;

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
        // 1. Ищем временный код из URL (он бывает только сразу после редиректа от Google)
        $authCode = $_GET['code'] ?? $params['code'] ?? null;

        if ($authCode) {
            // Если есть код из GET — это новый вход, его надо ОБМЕНИВАТЬ
            $this->authenticateWithCode($authCode, true);
            return;
        }

        // 2. Если кода в URL нет, проверяем сессию или базу на наличие готового ТОКЕНА
        $savedToken = $_SESSION['google_access_token'] ?? $this->loadTokenFromDatabase();

        if ($savedToken) {
            // Если токен есть — просто устанавливаем его в клиент, НЕ обмениваем
            $this->authenticateWithCode(is_array($savedToken) ? json_encode($savedToken) : $savedToken, false);
            return;
        }

        // 3. Если ничего нет — на авторизацию
        $this->redirectToGoogleAuth();
    }

    private function authenticateWithCode(string $codeOrToken, bool $isAuthCode = false): void
    {
        try {
            if ($isAuthCode) {
                // ОБМЕНИВАЕМ временный код на массив токенов
                $tokenArray = $this->googleClient->fetchAccessTokenWithAuthCode($codeOrToken);
                if (isset($tokenArray['error'])) {
                    throw new Exception("Google Exchange Error: " . $tokenArray['error']);
                }
                $this->googleClient->setAccessToken($tokenArray);
            } else {
                // ПРОСТО УСТАНАВЛИВАЕМ уже имеющийся токен
                $this->googleClient->setAccessToken($codeOrToken);
            }

            // ПОЛУЧАЕМ и ПРОВЕРЯЕМ токен
            $accessToken = $this->googleClient->getAccessToken();

            // ВАЖНО: Проверяем, что токен получен и не пустой
            if (!$accessToken || empty($accessToken['access_token'])) {
                LoggerFactory::getLogger()->error('No valid access token after authentication');
                throw new AuthenticationException('Не удалось получить access token');
            }

            // Проверяем, не истек ли токен
            if ($this->googleClient->isAccessTokenExpired()) {
                if (!$this->ensureValidToken()) {
                    $this->redirectToGoogleAuth();
                    return;
                }
                $accessToken = $this->googleClient->getAccessToken();
                if (!$accessToken || empty($accessToken['access_token'])) {
                    throw new AuthenticationException('Failed to refresh token');
                }
            }

            $_SESSION['google_access_token'] = $accessToken;

            $service = new Google_Service_Gmail($this->googleClient);
            $profile = $service->users->getProfile('me');

            if (!$profile || !$profile->getEmailAddress()) {
                throw new AuthenticationException('Failed to get user profile');
            }

            $userId = $this->saveOrAuthenticateUser($profile->getEmailAddress());
            $_SESSION['user_id'] = $userId;

            // Сохраняем токен ТОЛЬКО если он валидный
            $this->saveTokenToDatabase($accessToken);

            // Перенаправляем на главную или на страницу с письмами
            $this->redirect('/');

        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('Authentication failed', ['error' => $e->getMessage()]);
            $this->clearAuth();
            throw new AuthenticationException('Ошибка аутентификации: ' . $e->getMessage());
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

    private function getLastHistoryId(): ?string
    {
        try {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare("
            SELECT history_id 
            FROM sync_state 
            WHERE id = 1
        ");

            $stmt->execute();
            $result = $stmt->fetch();

            return $result ? $result['history_id'] : null;

        } catch (PDOException $e) {
            LoggerFactory::getLogger()->error('Failed to get last history id', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function updateHistoryId(string $historyId): void
    {
        try {
            $pdo = Database::getConnection();

            $pdo->exec("
            CREATE TABLE IF NOT EXISTS sync_state (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                history_id TEXT NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

            $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO sync_state (id, history_id, updated_at)
            VALUES (1, :history_id, CURRENT_TIMESTAMP)
        ");

            $stmt->execute([':history_id' => $historyId]);

        } catch (PDOException $e) {
            LoggerFactory::getLogger()->error('Failed to update history id', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function saveOrAuthenticateUser(string $email): int
    {
        try {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $stmt = $pdo->prepare("INSERT INTO users (email) VALUES (:email)");
                if (!$stmt->execute([':email' => $email])) {
                    throw new AuthenticationException('Failed to insert user');
                }
                $userId = $pdo->lastInsertId();
                if (!$userId) {
                    throw new AuthenticationException('Failed to get user ID after insert');
                }
            } else {
                $userId = $result['id'];
            }

            $_SESSION['user_id'] = $userId;
            return (int)$userId;

        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('Failed to save user', ['error' => $e->getMessage()]);
            throw new AuthenticationException('User authentication failed', 0, $e);
        }
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
                    LoggerFactory::getLogger()->error('Refresh token expired');
                    return;
                }
            }

            $service = new Google_Service_Gmail($this->googleClient);
            $user = 'me';
            LoggerFactory::getLogger()->info('saveOrAuthenticateUser');
            $userId = $this->saveOrAuthenticateUser(($service->users->getProfile($user)->getEmailAddress()));
            $lastHistoryId = $this->getLastHistoryId();

            $messages = [];

            if ($lastHistoryId) {
                $history = $service->users_history->listUsersHistory($user, [
                    'startHistoryId' => $lastHistoryId,
                    'maxResults' => 10
                ]);

                foreach ($history->getHistory() as $historyRecord) {
                    if ($historyRecord->getMessagesAdded()) {
                        foreach ($historyRecord->getMessagesAdded() as $messageAdded) {
                            $messages[] = $messageAdded->getMessage();
                        }
                    }
                }
            } else {
                $response = $service->users_messages->listUsersMessages($user, ['maxResults' => 10]);
                $messages = $response->getMessages();
            }

            if (empty($messages)) {
                LoggerFactory::getLogger()->info('No new emails found');
                $this->redirect('/');
                return;
            }

            $pdo = Database::getConnection();

            $stmt = $pdo->prepare("
                INSERT OR IGNORE INTO emails (message_id, user_id, subject, from_email, date, snippet)
                VALUES (:message_id, :user_id, :subject, :from_email, :date, :snippet)
            ");

            $newEmailsCount = 0;
            $currentHistoryId = null;

            foreach ($messages as $message) {
                $fullMessage = $service->users_messages->get($user, $message->getId());
                $emailData = $this->parseEmailData($fullMessage);

                $stmt->execute([
                    ':message_id' => $emailData['id'],
                    ':user_id' => $userId,
                    ':subject' => $emailData['subject'],
                    ':from_email' => $emailData['from'],
                    ':date' => $emailData['date'],
                    ':snippet' => $emailData['snippet']
                ]);

                if ($stmt->rowCount() > 0) {
                    $newEmailsCount++;
                }

                if (!$currentHistoryId || $fullMessage->getHistoryId() > $currentHistoryId) {
                    $currentHistoryId = $fullMessage->getHistoryId();
                }
            }

            if ($currentHistoryId) {
                $this->updateHistoryId($currentHistoryId);
            }

            LoggerFactory::getLogger()->info('Fetched new emails from Gmail', [
                'total_fetched' => count($messages),
                'new_saved' => $newEmailsCount,
                'history_id' => $currentHistoryId
            ]);

            $this->redirect('/');

        } catch (Google_Service_Exception $e) {
            $error = json_decode($e->getMessage(), true);
            LoggerFactory::getLogger()->error('Google API error', ['error' => $error]);
            throw AppException::fromGoogleError($error, $e);
        } catch (Exception $e) {
            LoggerFactory::getLogger()->error('Failed to fetch Gmail data', ['error' => $e->getMessage()]);
            throw new AppException('Не удалось загрузить письма.', 0, $e);
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
        try {
            $pdo = Database::getConnection();

            $accessToken = json_encode($token);
            $refreshToken = $token['refresh_token'] ?? null;
            $userId = $_SESSION['user_id'] ?? null;

            if (!$userId) {
                throw new AuthenticationException('User ID not found in session');
            }

            $stmt = $pdo->prepare("
            INSERT INTO google_tokens (user_id, access_token, refresh_token, updated_at)
            VALUES (:user_id, :access_token, :refresh_token, CURRENT_TIMESTAMP)
            ON CONFLICT(user_id) DO UPDATE SET
                access_token = :access_token_update,
                refresh_token = COALESCE(:refresh_token_update, refresh_token),
                updated_at = CURRENT_TIMESTAMP
        ");

            $stmt->execute([
                ':user_id'              => $userId,
                ':access_token'         => $accessToken,
                ':refresh_token'        => $refreshToken,
                ':access_token_update'  => $accessToken,
                ':refresh_token_update' => $refreshToken
            ]);

            LoggerFactory::getLogger()->info('Token saved to database successfully');

        } catch (PDOException $e) {
            LoggerFactory::getLogger()->error('Failed to save token to database', [
                'error' => $e->getMessage()
            ]);
            throw new AuthenticationException('Не удалось сохранить токен в базе данных.', 0, $e);
        }
    }

    private function updateTokenInDatabase(array $token): void
    {
        try {
            $pdo = Database::getConnection();

            $accessToken = json_encode($token);
            $refreshToken = $token['refresh_token'] ?? null;

            $stmt = $pdo->prepare("
            UPDATE google_tokens 
            SET access_token = :access_token,
                refresh_token = COALESCE(:refresh_token, refresh_token),
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id
        ");

            $userId = $_SESSION['user_id'] ?? 'default_user';

            $stmt->execute([
                ':user_id' => $userId,
                ':access_token' => $accessToken,
                ':refresh_token' => $refreshToken
            ]);

            if ($stmt->rowCount() === 0) {
                $this->saveTokenToDatabase($token);
            } else {
                LoggerFactory::getLogger()->info('Token updated in database successfully');
            }

        } catch (PDOException $e) {
            LoggerFactory::getLogger()->error('Failed to update token in database', [
                'error' => $e->getMessage()
            ]);
            throw new AuthenticationException('Не удалось обновить токен в базе данных.', 0, $e);
        }
    }

    public function loadTokenFromDatabase(): ?array
    {
        try {
            $pdo = Database::getConnection();

            $userId = $_SESSION['user_id'] ?? 'default_user';

            $stmt = $pdo->prepare("
            SELECT access_token, refresh_token 
            FROM google_tokens 
            WHERE user_id = :user_id
            ORDER BY updated_at DESC 
            LIMIT 1
        ");

            $stmt->execute([':user_id' => $userId]);
            $row = $stmt->fetch();

            if ($row) {
                $token = json_decode($row['access_token'], true);
                if ($row['refresh_token']) {
                    $token['refresh_token'] = $row['refresh_token'];
                }
                return $token;
            }

            return null;

        } catch (PDOException $e) {
            LoggerFactory::getLogger()->error('Failed to load token from database', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
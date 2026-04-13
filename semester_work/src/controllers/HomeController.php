<?php

namespace MyApp\Controllers;
require_once 'vendor/autoload.php';

use MyApp\Controllers\AbstractController;
use Google\Client;
use Google_Service_Gmail;

class HomeController extends AbstractController
{
    public function index(): void
    {
        session_start();
        // 1. Загружаем письма из последнего JSON-файла
        $emails = $this->loadEmailsFromJson();

        // 2. Устанавливаем заголовки (если нужно)
        header('Content-Type: text/html; charset=utf-8');

        // 3. Начинаем вывод HTML напрямую
        echo '<!DOCTYPE html>';
        echo '<html lang="ru">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Главная страница</title>';
        echo '<style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .email { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
                .email h3 { margin-top: 0; }
                .email-meta { color: #666; font-size: 0.9em; }
                .email-snippet { margin-top: 10px; }
            </style>';
        echo '</head>';
        echo '<body>';
        echo '<h1>Главная страница</h1>';

        if (empty($emails)) {
            echo '<p>Нет сохранённых писем.</p>';
            echo '<p><a href="/auth/gmail">Загрузить письма из Gmail</a></p>';
        } else {
            echo '<p>Всего писем: ' . count($emails) . '</p>';

            foreach ($emails as $email) {
                echo '<div class="email">';
                echo '<h3>' . htmlspecialchars($email['subject'] ?? 'Без темы') . '</h3>';
                echo '<div class="email-meta">';
                echo 'От: ' . htmlspecialchars($email['from'] ?? 'Неизвестно') . '<br>';
                echo 'Дата: ' . htmlspecialchars($email['date'] ?? '');
                echo '</div>';
                echo '<div class="email-snippet">';
                echo nl2br(htmlspecialchars($email['snippet'] ?? ''));
                echo '</div>';

                if (!empty($email['body'])) {
                    echo '<details>';
                    echo '<summary>Полный текст</summary>';
                    echo '<div style="margin-top:10px; white-space:pre-wrap;">';
                    echo nl2br(htmlspecialchars($email['body']));
                    echo '</div>';
                    echo '</details>';
                }

                echo '</div>'; // .email
            }
        }

        echo '</body>';
        echo '</html>';
    }

    private function loadEmailsFromJson(): array
    {
        $storageDir = __DIR__ . '/../../storage/emails';
        
        if (!is_dir($storageDir)) {
            return [];
        }
        
        $files = glob($storageDir . '/emails_*.json');
        if (empty($files)) {
            return [];
        }
        
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latestFile = $files[0];
        $content = file_get_contents($latestFile);
        $emails = json_decode($content, true);
        
        return is_array($emails) ? $emails : [];
    }
}


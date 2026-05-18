<?php

namespace MyApp\database;

use PDO;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {

            $dbPath = __DIR__ . '/app.sqlite';

            $pdo = new PDO("sqlite:$dbPath");

            $pdo->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $pdo->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            $pdo->exec("PRAGMA foreign_keys = ON");

            $pdo->exec("

                CREATE TABLE IF NOT EXISTS users
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    email TEXT NOT NULL UNIQUE
                );



                CREATE TABLE IF NOT EXISTS google_tokens
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    user_id INTEGER NOT NULL UNIQUE,

                    access_token TEXT,

                    refresh_token TEXT,

                    expires_at DATETIME,

                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                    FOREIGN KEY(user_id)
                        REFERENCES users(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );



                CREATE TABLE IF NOT EXISTS sync_state
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    user_id INTEGER NOT NULL UNIQUE,

                    history_id TEXT NOT NULL,

                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                    FOREIGN KEY(user_id)
                        REFERENCES users(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );



                CREATE TABLE IF NOT EXISTS senders
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    email TEXT NOT NULL UNIQUE,

                    display_name TEXT,

                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );



                CREATE TABLE IF NOT EXISTS emails
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    user_id INTEGER NOT NULL,

                    sender_id INTEGER NOT NULL,

                    gmail_message_id TEXT NOT NULL,

                    thread_id TEXT NOT NULL,

                    gmail_link TEXT,

                    recipient TEXT,

                    subject TEXT,

                    snippet TEXT,

                    received_at DATETIME NOT NULL,

                    history_id TEXT NOT NULL,

                    importance INTEGER NOT NULL DEFAULT 2
                    CHECK(importance IN (0,1,2,3,4)),

                    is_read INTEGER NOT NULL DEFAULT 0,

                    is_starred INTEGER NOT NULL DEFAULT 0,

                    is_deleted INTEGER NOT NULL DEFAULT 0,

                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                    UNIQUE(user_id, gmail_message_id),

                    FOREIGN KEY(user_id)
                        REFERENCES users(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,

                    FOREIGN KEY(sender_id)
                        REFERENCES senders(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );



                CREATE TABLE IF NOT EXISTS gmail_message_labels
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    gmail_label_id TEXT,

                    name TEXT NOT NULL,

                    color TEXT,

                    type TEXT NOT NULL
                    CHECK(type IN ('gmail', 'custom')),

                    created_by_user_id INTEGER,

                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                    FOREIGN KEY(created_by_user_id)
                        REFERENCES users(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );



                CREATE UNIQUE INDEX IF NOT EXISTS idx_unique_gmail_label
                ON gmail_message_labels(gmail_label_id)
                WHERE gmail_label_id IS NOT NULL;



                CREATE UNIQUE INDEX IF NOT EXISTS idx_unique_custom_label
                ON gmail_message_labels(created_by_user_id, name)
                WHERE type = 'custom';



                CREATE TABLE IF NOT EXISTS email_labels
                (
                    email_id INTEGER NOT NULL,

                    label_id INTEGER NOT NULL,

                    PRIMARY KEY(email_id, label_id),

                    FOREIGN KEY(email_id)
                        REFERENCES emails(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,

                    FOREIGN KEY(label_id)
                        REFERENCES gmail_message_labels(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );



                CREATE TABLE IF NOT EXISTS todo_lists
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    user_id INTEGER NOT NULL,

                    name TEXT NOT NULL,

                    color TEXT,

                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                    FOREIGN KEY(user_id)
                        REFERENCES users(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );



                CREATE UNIQUE INDEX IF NOT EXISTS idx_unique_todo_list
                ON todo_lists(user_id, name);



                CREATE TABLE IF NOT EXISTS todo_tasks
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    user_id INTEGER NOT NULL,

                    list_id INTEGER,

                    email_id INTEGER,

                    title TEXT NOT NULL,

                    description TEXT,

                    priority INTEGER NOT NULL DEFAULT 2
                    CHECK(priority IN (0,1,2,3)),

                    status TEXT NOT NULL DEFAULT 'pending'
                    CHECK(status IN (
                        'pending',
                        'in_progress',
                        'completed',
                        'cancelled'
                    )),

                    due_date DATETIME,

                    reminder_at DATETIME,

                    completed_at DATETIME,

                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                    FOREIGN KEY(user_id)
                        REFERENCES users(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,

                    FOREIGN KEY(list_id)
                        REFERENCES todo_lists(id)
                        ON DELETE SET NULL
                        ON UPDATE CASCADE,

                    FOREIGN KEY(email_id)
                        REFERENCES emails(id)
                        ON DELETE SET NULL
                        ON UPDATE CASCADE
                );

                CREATE TABLE IF NOT EXISTS todo_tags
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    user_id INTEGER NOT NULL,

                    name TEXT NOT NULL,

                    color TEXT,

                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                    FOREIGN KEY(user_id)
                        REFERENCES users(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );



                CREATE UNIQUE INDEX IF NOT EXISTS idx_unique_todo_tag
                ON todo_tags(user_id, name);



                CREATE TABLE IF NOT EXISTS todo_task_tags
                (
                    task_id INTEGER NOT NULL,

                    tag_id INTEGER NOT NULL,

                    PRIMARY KEY(task_id, tag_id),

                    FOREIGN KEY(task_id)
                        REFERENCES todo_tasks(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,

                    FOREIGN KEY(tag_id)
                        REFERENCES todo_tags(id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );
                
                CREATE TABLE IF NOT EXISTS user_sender_settings
                (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                    user_id INTEGER NOT NULL,
                    sender_id INTEGER NOT NULL,
                
                    importance INTEGER NOT NULL DEFAULT 2,
                    is_trusted INTEGER NOT NULL DEFAULT 0,
                    is_spam INTEGER NOT NULL DEFAULT 0,
                
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY(sender_id) REFERENCES senders(id) ON DELETE CASCADE,
                
                    UNIQUE(user_id, sender_id)
                );

            ");

            self::$connection = $pdo;
        }

        return self::$connection;
    }
}
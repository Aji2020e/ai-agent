-- AI Coding Assistant - Database Setup
-- Jalankan file ini di MySQL/MariaDB untuk setup manual

CREATE DATABASE IF NOT EXISTS `ai_agent` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ai_agent`;

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: chat_history
CREATE TABLE IF NOT EXISTS `chat_history` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `session_id` VARCHAR(36) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('user', 'assistant', 'system') NOT NULL,
    `content` LONGTEXT NOT NULL,
    `tokens_used` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `attachment_name` VARCHAR(255) DEFAULT NULL,
    `attachment_path` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `session_id` (`session_id`),
    CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: projects
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `path` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `fk_project_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `key` (`key`),
    CONSTRAINT `fk_setting_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Admin User (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `is_active`, `created_at`, `updated_at`)
VALUES ('admin', 'admin@aiassistant.local', '$2y$10$SEheSvFiDVsgm6N4R4XWFeJ9vPLadZ6uML6/etKzZbKL16esdJXW.', 'Administrator', 'admin', 1, NOW(), NOW());

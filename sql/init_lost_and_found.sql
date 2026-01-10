-- Lost & Found DB schema for KenSpecter/Lost_and_found
-- Save as sql/init_lost_and_found.sql and import into phpMyAdmin or mysql CLI.

CREATE DATABASE IF NOT EXISTS `lost_and_found` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `lost_and_found`;

-- users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `role` ENUM('admin','staff','student','guest') NOT NULL DEFAULT 'student',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- categories (e.g., electronics, clothing)
CREATE TABLE IF NOT EXISTS `categories` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL UNIQUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- locations (e.g., library, cafeteria)
CREATE TABLE IF NOT EXISTS `locations` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL UNIQUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- items (lost or found entries)
CREATE TABLE IF NOT EXISTS `items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(120) NOT NULL,
  `description` TEXT,
  `status` ENUM('lost','found') NOT NULL,
  `category_id` TINYINT UNSIGNED DEFAULT NULL,
  `location_id` TINYINT UNSIGNED DEFAULT NULL,
  `reporter_id` INT UNSIGNED DEFAULT NULL,
  `date_reported` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `is_returned` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category_id`),
  KEY `idx_location` (`location_id`),
  KEY `idx_reporter` (`reporter_id`),
  CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_items_location` FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_items_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- optional table to track potential matches between lost and found items
CREATE TABLE IF NOT EXISTS `matches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lost_item_id` INT UNSIGNED NOT NULL,
  `found_item_id` INT UNSIGNED NOT NULL,
  `confidence` DECIMAL(5,2) DEFAULT NULL, -- optional numeric score
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_matches_lost` FOREIGN KEY (`lost_item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_found` FOREIGN KEY (`found_item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seed some categories and locations
INSERT IGNORE INTO `categories` (`name`) VALUES
('Electronics'), ('Clothing'), ('Books'), ('Stationery'), ('Accessories');

INSERT IGNORE INTO `locations` (`name`) VALUES
('Library'), ('Cafeteria'), ('Gym'), ('Reception'), ('Classroom');

-- Note: Do not insert plaintext passwords. Create users with a properly hashed password via PHP's password_hash()
-- Example user entry (password_hash should be created by the app or a script):
-- INSERT INTO `users` (`username`, `password_hash`, `email`, `role`) VALUES ('admin', '$2y$...', 'admin@example.com', 'admin');

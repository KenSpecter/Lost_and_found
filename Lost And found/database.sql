CREATE DATABASE IF NOT EXISTS lost_found_system;
USE lost_found_system;
CREATE TABLE users (
id INT PRIMARY KEY AUTO_INCREMENT,
username VARCHAR(100) NOT NULL UNIQUE,
email VARCHAR(255) NOT NULL UNIQUE,
password VARCHAR(255) NOT NULL,
role ENUM('admin', 'user') DEFAULT 'user',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE items (
id INT PRIMARY KEY AUTO_INCREMENT,
name VARCHAR(255) NOT NULL,
description TEXT NOT NULL,
category VARCHAR(100) NOT NULL,
type ENUM('lost', 'found') NOT NULL,
date_lost DATE,
date_found DATE,
date_returned DATE,
status ENUM('pending', 'returned') DEFAULT 'pending',
location VARCHAR(255) NOT NULL,
contact_email VARCHAR(255) NOT NULL,
contact_phone VARCHAR(20) NOT NULL,
school_id VARCHAR(50) NOT NULL,
photo_url VARCHAR(500),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX idx_status (status),
INDEX idx_category (category),
INDEX idx_type (type),
INDEX idx_created_at (created_at)
);
CREATE TABLE notifications (
id INT PRIMARY KEY AUTO_INCREMENT,
item_id INT NOT NULL,
recipient_email VARCHAR(255) NOT NULL,
message TEXT NOT NULL,
type ENUM('status_change', 'match_found') NOT NULL,
is_read BOOLEAN DEFAULT FALSE,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);
INSERT INTO users (username, email, password, role)
VALUES ('admin', 'admin@lostfound.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
DELIMITER $$
CREATE EVENT auto_delete_old_items
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
DELETE FROM items
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END$$
DELIMITER ;
SET GLOBAL event_scheduler = ON;
<?php
// create_admin.php - run once to add an admin user
// Usage: php php/create_admin.php

$cfg = require __DIR__ . '/config.php';
$pdo = new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}", $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$username = 'admin';
$email = 'admin@example.com';
$password = getenv('ADMIN_PASSWORD') ?: 'ChangeMeStrong!'; // set ADMIN_PASSWORD env var before running for safety
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, 'admin')");
try {
    $stmt->execute([$username, $hash, $email]);
    echo "Admin user created. Username: $username\n";
} catch (PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage() . "\n";
}

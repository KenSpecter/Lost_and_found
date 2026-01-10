<?php
// config.php - DB config for local XAMPP
// Put this file in your project's include/config location and include_require it where needed.

return [
    'db_host' => '127.0.0.1',    // or 'localhost'
    'db_name' => 'lost_and_found',
    'db_user' => 'root',
    'db_pass' => '',             // XAMPP default root password is empty
    'db_charset' => 'utf8mb4',
];

// Example usage:
// $cfg = require __DIR__ . '/config.php';
// $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}";
// $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

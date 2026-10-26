<?php
$host = 'localhost';
$db   = 'task_manager_db';
$user = 'root';
$pass = 'root'; // Leave blank if you are using XAMPP/MAMP default local root settings
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Log the error securely in production, show a clean message locally
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
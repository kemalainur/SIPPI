<?php
$host = 'localhost';
$db   = 'sippi';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);
    echo "SUCCESS: Connected to database successfully!\n";
} catch (PDOException $e) {
    echo "PDO Error Code: " . $e->getCode() . "\n";
    echo "PDO Error Message: " . $e->getMessage() . "\n";
}

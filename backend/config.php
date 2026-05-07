<?php
session_start();
// Database configuration — edit these values for your environment
$DB_HOST = '127.0.0.1';
$DB_NAME = 'water_mission';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database connection error: ' . htmlspecialchars($e->getMessage());
    exit;
}

header('Access-Control-Allow-Origin: http://localhost:4321');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
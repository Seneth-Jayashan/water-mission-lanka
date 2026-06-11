<?php
session_start();
// Database configuration — edit these values for your environment
$DB_HOST = '127.0.0.1';
$DB_NAME = 'water_mission';
$DB_USER = 'root';
$DB_PASS = '';

// Initial admin credentials are read from environment variables.
// Set these in your web server, shell profile, or launch script.
$ADMIN_USERNAME = getenv('ADMIN_USERNAME');
$ADMIN_PASSWORD = getenv('ADMIN_PASSWORD');

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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
<?php
require_once __DIR__ . '/../../functions.php';
global $pdo;

header('Content-Type: application/json');

$stmt = $pdo->query('SELECT COUNT(*) FROM admins');
$adminCount = (int) $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'adminExists' => $adminCount > 0]);
    exit;
}

// Expect JSON body
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

if ($action === 'create_admin') {
    if ($adminCount > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'An admin user already exists. Only one admin account can exist.']);
        exit;
    }
    
    $username = getenv('ADMIN_USERNAME') ?: 'admin';
    $password = getenv('ADMIN_PASSWORD') ?: 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    try {
        $stmt->execute([$username, $hash]);
        echo json_encode(['success' => true, 'message' => 'Admin created successfully. You can now log in.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

$username = $input['username'] ?? null;
$password = $input['password'] ?? null;

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing credentials']);
    exit;
}

if (loginUser($username, $password)) {
    // Return success — session cookie will be set if CORS allows credentials
    echo json_encode(['success' => true]);
    exit;
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
    exit;
}

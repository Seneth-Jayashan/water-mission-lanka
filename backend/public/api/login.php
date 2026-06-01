<?php
require_once __DIR__ . '/../../functions.php';

// Expect JSON body
$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? null;
$password = $input['password'] ?? null;

header('Content-Type: application/json');

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

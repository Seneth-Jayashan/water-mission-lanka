<?php
require_once __DIR__ . '/../../functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isLogged()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

global $pdo;

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing product id']);
    exit;
}

// Verify product exists
$stmtCheck = $pdo->prepare('SELECT id FROM products WHERE id = ?');
$stmtCheck->execute([$id]);
if (!$stmtCheck->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit;
}

$uploadsDir = __DIR__ . '/../../assets/uploads/';

// Delete image files from product_images
$stmtImgs = $pdo->prepare('SELECT filename FROM product_images WHERE product_id = ?');
$stmtImgs->execute([$id]);
foreach ($stmtImgs->fetchAll() as $row) {
    if (!empty($row['filename'])) {
        @unlink($uploadsDir . $row['filename']);
    }
}

// Delete single-thumb legacy image if still used
$stmtLegacy = $pdo->prepare('SELECT image FROM products WHERE id = ?');
$stmtLegacy->execute([$id]);
$legacy = $stmtLegacy->fetchColumn();
if ($legacy) {
    @unlink($uploadsDir . $legacy);
}

// Clean up DB records
$pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);

echo json_encode(['success' => true]);

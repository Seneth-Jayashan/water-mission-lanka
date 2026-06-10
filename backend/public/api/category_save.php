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
$uploadsDir = __DIR__ . '/../../assets/uploads';

// Determine if this is a JSON request or multipart (for image upload)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    // JSON body — used for delete action only
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    // Multipart or form-encoded — used for create/update with image
    $action = $_POST['action'] ?? '';
    $input = $_POST;
}

if (!in_array($action, ['create', 'update', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action. Must be create, update, or delete.']);
    exit;
}

if ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category id is required.']);
        exit;
    }
    // Delete category image file if exists
    $stmt = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $oldImage = $stmt->fetchColumn();
    if ($oldImage && file_exists($uploadsDir . '/' . $oldImage)) {
        @unlink($uploadsDir . '/' . $oldImage);
    }
    $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// Create or Update
$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$id = (int)($input['id'] ?? 0);

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Category name is required.']);
    exit;
}

// Handle image upload
$imageFilename = null;
$hasNewImage = false;

if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $maxBytes = 2 * 1024 * 1024;
    $file = $_FILES['image'];

    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Image must be between 1 byte and 2MB.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Only JPG, PNG, and WEBP images are allowed.']);
        exit;
    }

    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    $imageFilename = 'cat_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    $destination = $uploadsDir . '/' . $imageFilename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded image.']);
        exit;
    }
    $hasNewImage = true;
}

if ($action === 'create') {
    $stmt = $pdo->prepare('INSERT INTO categories (name, description, image) VALUES (?, ?, ?)');
    $stmt->execute([$name, $description, $imageFilename]);
    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($action === 'update') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category id is required.']);
        exit;
    }

    // If new image uploaded, delete old one
    if ($hasNewImage) {
        $stmt = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $oldImage = $stmt->fetchColumn();
        if ($oldImage && file_exists($uploadsDir . '/' . $oldImage)) {
            @unlink($uploadsDir . '/' . $oldImage);
        }
        $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ?, image = ? WHERE id = ?');
        $stmt->execute([$name, $description, $imageFilename, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $description, $id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

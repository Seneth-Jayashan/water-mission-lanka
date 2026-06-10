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

function validateAndSaveProductImage(array $file, string $uploadsDir, array &$errors): ?string
{
    $maxBytes = 2 * 1024 * 1024;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'One of the selected images failed to upload.';
        return null;
    }
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        $errors[] = 'Each image must be between 1 byte and 2MB.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
        return null;
    }

    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    $destination = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = 'Failed to move one uploaded image.';
        return null;
    }

    return $filename;
}

$errors = [];

$name = trim($_POST['name'] ?? '');
$desc = trim($_POST['description'] ?? '');
$priceInput = trim((string)($_POST['price'] ?? '0'));
$catIds = isset($_POST['category_ids']) ? array_map('intval', (array)$_POST['category_ids']) : [];
$removeImageIds = isset($_POST['remove_image_ids']) ? array_map('intval', (array)$_POST['remove_image_ids']) : [];
$removeFilenames = isset($_POST['remove_filenames']) ? (array)$_POST['remove_filenames'] : [];
$editId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Validation
if ($name === '' || strlen($name) < 2) {
    $errors[] = 'Product name is required and must be at least 2 characters.';
}
if (!is_numeric($priceInput) || (float)$priceInput < 0) {
    $errors[] = 'Price must be a non-negative number.';
}

$price = (float)$priceInput;
$uploadsDir = __DIR__ . '/../../assets/uploads';
$newImages = [];

// Process uploaded images
if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
    // count incoming files (exclude NO_FILE entries)
    $incomingCount = 0;
    $fileCount = count($_FILES['images']['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if (isset($_FILES['images']['error'][$i]) && $_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $incomingCount++;
    }

    // determine existing images for edit mode
    $existingCount = 0;
    if ($editId > 0) {
        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
        $stmtCount->execute([$editId]);
        $existingCount = (int)$stmtCount->fetchColumn();
    }

    // calculate removals supplied by client (ids + filenames)
    $removalsCount = 0;
    if (!empty($removeImageIds)) $removalsCount += count($removeImageIds);
    if (!empty($removeFilenames)) $removalsCount += count($removeFilenames);

    $projectedExisting = max(0, $existingCount - $removalsCount);

    if ($projectedExisting + $incomingCount > 6) {
        $errors[] = 'You can upload up to 6 images per product (existing + new).';
    } else {
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $single = [
                'name'     => $_FILES['images']['name'][$i],
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i],
            ];
            $saved = validateAndSaveProductImage($single, $uploadsDir, $errors);
            if ($saved) {
                $newImages[] = $saved;
            }
        }
    }
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Create or update the product
if ($editId > 0) {
    $stmt = $pdo->prepare('UPDATE products SET name=?, description=?, price=?, updated_at=NOW() WHERE id=?');
    $stmt->execute([$name, $desc, $price, $editId]);
    $productId = $editId;
} else {
    $stmt = $pdo->prepare('INSERT INTO products (name, description, price) VALUES (?,?,?)');
    $stmt->execute([$name, $desc, $price]);
    $productId = (int)$pdo->lastInsertId();
}

// Sync categories
$pdo->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$productId]);
$insCat = $pdo->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (?,?)');
foreach ($catIds as $cid) {
    if ($cid > 0) {
        $insCat->execute([$productId, $cid]);
    }
}

// Remove selected images
if (!empty($removeImageIds)) {
    $selRm = $pdo->prepare('SELECT id, filename FROM product_images WHERE product_id = ? AND id = ?');
    $delRm = $pdo->prepare('DELETE FROM product_images WHERE product_id = ? AND id = ?');
    foreach ($removeImageIds as $rmId) {
        $selRm->execute([$productId, $rmId]);
        $rmRow = $selRm->fetch();
        if ($rmRow) {
            @unlink($uploadsDir . '/' . $rmRow['filename']);
            $delRm->execute([$productId, $rmId]);
        }
    }
}

// remove by filename (sent by frontend when product list doesn't include image ids)
if (!empty($removeFilenames)) {
    $selByName = $pdo->prepare('SELECT id, filename FROM product_images WHERE product_id = ? AND filename = ?');
    $delByName = $pdo->prepare('DELETE FROM product_images WHERE product_id = ? AND filename = ?');
    foreach ($removeFilenames as $fname) {
        $selByName->execute([$productId, $fname]);
        $row = $selByName->fetch();
        if ($row) {
            @unlink($uploadsDir . '/' . $row['filename']);
            $delByName->execute([$productId, $fname]);
        }
    }
}

// Insert new images
$sortBaseStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?');
$sortBaseStmt->execute([$productId]);
$sortOrder = ((int)$sortBaseStmt->fetchColumn()) + 1;
$insImg = $pdo->prepare('INSERT INTO product_images (product_id, filename, sort_order) VALUES (?,?,?)');
foreach ($newImages as $filename) {
    $insImg->execute([$productId, $filename, $sortOrder]);
    $sortOrder++;
}

// Update thumbnail
$thumbStmt = $pdo->prepare('SELECT filename FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1');
$thumbStmt->execute([$productId]);
$thumb = $thumbStmt->fetchColumn() ?: null;
$pdo->prepare('UPDATE products SET image = ? WHERE id = ?')->execute([$thumb, $productId]);

echo json_encode(['success' => true, 'product_id' => $productId]);

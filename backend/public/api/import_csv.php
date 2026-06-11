<?php
require_once __DIR__ . '/../../functions.php';
requireAuth();
global $pdo;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Please upload a valid CSV file.']);
    exit;
}

$tmp = $_FILES['csv_file']['tmp_name'];
$fh = fopen($tmp, 'r');
if (!$fh) {
    echo json_encode(['success' => false, 'error' => 'Unable to open uploaded CSV.']);
    exit;
}

$header = fgetcsv($fh, 0, ',', '"', '\\');
if (!$header) {
    echo json_encode(['success' => false, 'error' => 'CSV header row is missing.']);
    exit;
}

$index = array_flip($header);
$required = ['Product ID', 'Product Name', 'Product Description', 'Price (LKR)', 'Categories', 'Images'];
foreach ($required as $col) {
    if (!isset($index[$col])) {
        echo json_encode(['success' => false, 'error' => 'Missing required CSV column: ' . $col]);
        exit;
    }
}

$imported = 0;
$updated = 0;
$errors = [];

$pdo->beginTransaction();
try {
    $checkProduct = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $insertProduct = $pdo->prepare('INSERT INTO products (name, description, price) VALUES (?,?,?)');
    $updateProduct = $pdo->prepare('UPDATE products SET name = ?, description = ?, price = ? WHERE id = ?');
    
    $insertCategory = $pdo->prepare('INSERT IGNORE INTO categories (name) VALUES (?)');
    $selectCategory = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    
    $deletePc = $pdo->prepare('DELETE FROM product_categories WHERE product_id = ?');
    $insertPc = $pdo->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (?,?)');

    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        $idRaw = trim((string)($row[$index['Product ID']] ?? ''));
        $name = trim((string)($row[$index['Product Name']] ?? ''));
        $description = trim((string)($row[$index['Product Description']] ?? ''));
        $priceRaw = trim((string)($row[$index['Price (LKR)']] ?? '0'));
        $categoriesRaw = trim((string)($row[$index['Categories']] ?? ''));
        $imagesRaw = trim((string)($row[$index['Images']] ?? ''));

        if ($name === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
            continue;
        }

        $productId = null;
        $isUpdate = false;

        if ($idRaw !== '' && is_numeric($idRaw)) {
            $checkProduct->execute([(int)$idRaw]);
            if ($checkProduct->fetchColumn()) {
                $isUpdate = true;
                $productId = (int)$idRaw;
            }
        }

        if ($isUpdate) {
            $updateProduct->execute([$name, $description, (float)$priceRaw, $productId]);
            $deletePc->execute([$productId]);
            $updated++;
        } else {
            $insertProduct->execute([$name, $description, (float)$priceRaw]);
            $productId = (int)$pdo->lastInsertId();
            $imported++;
        }

        if ($categoriesRaw !== '') {
            $parts = array_filter(array_map('trim', explode('|', $categoriesRaw)));
            foreach ($parts as $catName) {
                if (empty($catName)) continue;
                $insertCategory->execute([$catName]);
                $selectCategory->execute([$catName]);
                $catId = (int)$selectCategory->fetchColumn();
                if ($catId > 0) {
                    $insertPc->execute([$productId, $catId]);
                }
            }
        }

        // Handle images
        if ($imagesRaw !== '') {
            $imgParts = array_filter(array_map('trim', explode('|', $imagesRaw)));
            if (!empty($imgParts)) {
                $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
                $insImg = $pdo->prepare('INSERT INTO product_images (product_id, filename, sort_order) VALUES (?,?,?)');
                $sortOrder = 0;
                foreach ($imgParts as $fname) {
                    $insImg->execute([$productId, $fname, $sortOrder]);
                    $sortOrder++;
                }
                $pdo->prepare('UPDATE products SET image = ? WHERE id = ?')->execute([$imgParts[0], $productId]);
            }
        } else {
            // Clear images if explicitly empty
            $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
            $pdo->prepare('UPDATE products SET image = NULL WHERE id = ?')->execute([$productId]);
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => "CSV processed. Imported: $imported, Updated: $updated"
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Import failed: ' . $e->getMessage()]);
}

fclose($fh);

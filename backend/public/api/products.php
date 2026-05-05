<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 24;
$limit = max(1, min($limit, 100));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

$q = trim((string)($_GET['q'] ?? ''));
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$sort = (string)($_GET['sort'] ?? 'newest');

$allowedSort = [
    'newest' => 'p.id DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC',
];
$orderBy = $allowedSort[$sort] ?? $allowedSort['newest'];

$where = [];
$params = [];
if ($id > 0) {
    $where[] = 'p.id = ?';
    $params[] = $id;
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($categoryId > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM product_categories pcf WHERE pcf.product_id = p.id AND pcf.category_id = ?)';
    $params[] = $categoryId;
}
if ($minPrice !== null) {
    $where[] = 'p.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 'p.price <= ?';
    $params[] = $maxPrice;
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$countSql = 'SELECT COUNT(*) FROM products p' . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$dataSql = 'SELECT p.id, p.name, p.description, p.price, p.created_at,
    COALESCE((SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order, pi.id LIMIT 1), p.image) AS thumbnail,
    (SELECT COUNT(*) FROM product_images pi2 WHERE pi2.product_id = p.id) AS image_count
    FROM products p' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
$dataStmt = $pdo->prepare($dataSql);
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

$productIds = array_map(static fn($r) => (int)$r['id'], $rows);
$categoriesByProduct = [];
if (!empty($productIds)) {
    $in = implode(',', array_fill(0, count($productIds), '?'));
    $catStmt = $pdo->prepare('SELECT pc.product_id, c.id, c.name
      FROM product_categories pc
      INNER JOIN categories c ON c.id = pc.category_id
      WHERE pc.product_id IN (' . $in . ')
      ORDER BY c.name');
    $catStmt->execute($productIds);
    foreach ($catStmt->fetchAll() as $catRow) {
        $pid = (int)$catRow['product_id'];
        if (!isset($categoriesByProduct[$pid])) {
            $categoriesByProduct[$pid] = [];
        }
        $categoriesByProduct[$pid][] = [
            'id' => (int)$catRow['id'],
            'name' => $catRow['name'],
        ];
    }

    $imgStmt = $pdo->prepare('SELECT product_id, filename FROM product_images WHERE product_id IN (' . $in . ') ORDER BY sort_order, id');
    $imgStmt->execute($productIds);
    $imagesByProduct = [];
    foreach ($imgStmt->fetchAll() as $imgRow) {
        $pid = (int)$imgRow['product_id'];
        if (!isset($imagesByProduct[$pid])) {
            $imagesByProduct[$pid] = [];
        }
        $imagesByProduct[$pid][] = $imgRow['filename'];
    }
} else {
    $imagesByProduct = [];
}

$baseImagePath = '/backend/assets/uploads/';
$products = [];
foreach ($rows as $row) {
    $pid = (int)$row['id'];
    $thumb = !empty($row['thumbnail']) ? $baseImagePath . $row['thumbnail'] : null;
    $images = [];
    foreach (($imagesByProduct[$pid] ?? []) as $filename) {
        $images[] = $baseImagePath . $filename;
    }
    $products[] = [
        'id' => $pid,
        'name' => $row['name'],
        'description' => $row['description'],
        'price' => (float)$row['price'],
        'thumbnail' => $thumb,
        'image_count' => (int)$row['image_count'],
        'images' => $images,
        'categories' => $categoriesByProduct[$pid] ?? [],
    ];
}

echo json_encode([
    'meta' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => (int)ceil($total / max($limit, 1)),
    ],
    'products' => $products,
], JSON_UNESCAPED_SLASHES);

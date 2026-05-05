<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$stmt = $pdo->query('SELECT c.id, c.name, COUNT(pc.product_id) AS product_count
  FROM categories c
  LEFT JOIN product_categories pc ON pc.category_id = c.id
  GROUP BY c.id
  ORDER BY c.name');

$categories = [];
foreach ($stmt->fetchAll() as $row) {
    $categories[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'product_count' => (int)$row['product_count'],
    ];
}

echo json_encode(['categories' => $categories], JSON_UNESCAPED_SLASHES);

<?php
require_once __DIR__ . '/../functions.php';
requireAuth();
global $pdo;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="products_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Product ID', 'Product Name', 'Product Description', 'Price (LKR)', 'Categories', 'Images'], ',', '"', '\\');

$sql = 'SELECT p.id, p.name, p.description, p.price,
  GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR "|") AS categories,
  GROUP_CONCAT(DISTINCT pi.filename ORDER BY pi.id SEPARATOR "|") AS images
  FROM products p
  LEFT JOIN product_categories pc ON p.id = pc.product_id
  LEFT JOIN categories c ON c.id = pc.category_id
  LEFT JOIN product_images pi ON p.id = pi.product_id
  GROUP BY p.id
  ORDER BY p.id DESC';

foreach ($pdo->query($sql) as $row) {
    fputcsv($out, [
        $row['id'],
        $row['name'],
        $row['description'],
        $row['price'],
        $row['categories'] ?? '',
        $row['images'] ?? '',
    ], ',', '"', '\\');
}

fclose($out);
exit;

<?php
require_once __DIR__ . '/../functions.php';
requireAuth();
global $pdo;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="products_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['name', 'description', 'price', 'categories']);

$sql = 'SELECT p.id, p.name, p.description, p.price,
  GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR "|") AS categories
  FROM products p
  LEFT JOIN product_categories pc ON p.id = pc.product_id
  LEFT JOIN categories c ON c.id = pc.category_id
  GROUP BY p.id
  ORDER BY p.id DESC';

foreach ($pdo->query($sql) as $row) {
    fputcsv($out, [
        $row['name'],
        $row['description'],
        $row['price'],
        $row['categories'] ?? '',
    ]);
}

fclose($out);
exit;

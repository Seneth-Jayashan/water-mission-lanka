<?php
require_once __DIR__ . '/../functions.php';
requireAuth();
global $pdo;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $uploadsDir = __DIR__ . '/../assets/uploads/';
        $stmtImgs = $pdo->prepare('SELECT filename FROM product_images WHERE product_id = ?');
        $stmtImgs->execute([$id]);
        foreach ($stmtImgs->fetchAll() as $row) {
            if (!empty($row['filename'])) {
                @unlink($uploadsDir . $row['filename']);
            }
        }

        // delete single-thumb legacy image if still used
        $stmtLegacy = $pdo->prepare('SELECT image FROM products WHERE id = ?');
        $stmtLegacy->execute([$id]);
        $legacy = $stmtLegacy->fetchColumn();
        if ($legacy) {
            @unlink($uploadsDir . $legacy);
        }

        $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$id]);
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
    }
}
header('Location: products.php');
exit;

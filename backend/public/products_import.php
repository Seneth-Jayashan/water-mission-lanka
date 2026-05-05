<?php
require_once __DIR__ . '/../functions.php';
requireAuth();
global $pdo;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a valid CSV file.';
    } else {
        $tmp = $_FILES['csv_file']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) {
            $errors[] = 'Unable to open uploaded CSV.';
        } else {
            $header = fgetcsv($fh);
            if (!$header) {
                $errors[] = 'CSV header row is missing.';
            } else {
                $index = array_flip($header);
                $required = ['name', 'description', 'price', 'categories'];
                foreach ($required as $col) {
                    if (!isset($index[$col])) {
                        $errors[] = 'Missing required CSV column: ' . $col;
                    }
                }
            }

            $imported = 0;
            if (!$errors) {
                $pdo->beginTransaction();
                try {
                    $insertProduct = $pdo->prepare('INSERT INTO products (name, description, price) VALUES (?,?,?)');
                    $insertCategory = $pdo->prepare('INSERT IGNORE INTO categories (name) VALUES (?)');
                    $selectCategory = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
                    $insertPc = $pdo->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (?,?)');

                    while (($row = fgetcsv($fh)) !== false) {
                        $name = trim((string)($row[$index['name']] ?? ''));
                        $description = trim((string)($row[$index['description']] ?? ''));
                        $priceRaw = trim((string)($row[$index['price']] ?? '0'));
                        $categoriesRaw = trim((string)($row[$index['categories']] ?? ''));

                        if ($name === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
                            continue;
                        }

                        $insertProduct->execute([$name, $description, (float)$priceRaw]);
                        $productId = (int)$pdo->lastInsertId();

                        if ($categoriesRaw !== '') {
                            $parts = array_filter(array_map('trim', explode('|', $categoriesRaw)));
                            foreach ($parts as $catName) {
                                $insertCategory->execute([$catName]);
                                $selectCategory->execute([$catName]);
                                $catId = (int)$selectCategory->fetchColumn();
                                if ($catId > 0) {
                                    $insertPc->execute([$productId, $catId]);
                                }
                            }
                        }

                        $imported++;
                    }

                    $pdo->commit();
                    $success = 'CSV import completed. Imported products: ' . $imported;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'Import failed: ' . $e->getMessage();
                }
            }

            fclose($fh);
        }
    }
}
?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<div class="container" style="max-width: 780px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Import Products (CSV)</h2>
    <a href="products.php" class="btn btn-outline-secondary">Back to Products</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
          <li><?php echo h($error); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <p class="mb-2">CSV headers must be exactly:</p>
      <code>name,description,price,categories</code>
      <p class="mt-3 mb-2">Categories can contain multiple values separated by <code>|</code>.</p>
      <p class="mb-3">Example categories cell: <code>Water Pumps|Industrial</code></p>

      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">CSV File</label>
          <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" required>
        </div>
        <button class="btn btn-primary">Import CSV</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

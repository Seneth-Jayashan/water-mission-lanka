<?php
require_once __DIR__ . '/../functions.php';
requireAuth();
global $pdo;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$product = null;
$selectedCategoryIds = [];
$productImages = [];
$errors = [];
$old = [
    'name' => '',
    'description' => '',
    'price' => '0.00',
    'category_ids' => [],
];

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

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product) {
        $old['name'] = $product['name'];
        $old['description'] = $product['description'];
        $old['price'] = $product['price'];

        $stmtCat = $pdo->prepare('SELECT category_id FROM product_categories WHERE product_id = ?');
        $stmtCat->execute([$id]);
        $selectedCategoryIds = array_map('intval', array_column($stmtCat->fetchAll(), 'category_id'));
        $old['category_ids'] = $selectedCategoryIds;

        $stmtImg = $pdo->prepare('SELECT id, filename FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
        $stmtImg->execute([$id]);
        $productImages = $stmtImg->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $priceInput = trim((string)($_POST['price'] ?? '0'));
    $catIds = isset($_POST['category_ids']) ? array_map('intval', (array)$_POST['category_ids']) : [];
    $removeImageIds = isset($_POST['remove_image_ids']) ? array_map('intval', (array)$_POST['remove_image_ids']) : [];

    $old['name'] = $name;
    $old['description'] = $desc;
    $old['price'] = $priceInput;
    $old['category_ids'] = $catIds;

    if ($name === '' || strlen($name) < 2) {
        $errors[] = 'Product name is required and must be at least 2 characters.';
    }
    if (!is_numeric($priceInput) || (float)$priceInput < 0) {
        $errors[] = 'Price must be a non-negative number.';
    }

    $price = (float)$priceInput;
    $uploadsDir = __DIR__ . '/../assets/uploads';
    $newImages = [];

    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $fileCount = count($_FILES['images']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $single = [
                'name' => $_FILES['images']['name'][$i],
                'type' => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error' => $_FILES['images']['error'][$i],
                'size' => $_FILES['images']['size'][$i],
            ];
            $saved = validateAndSaveProductImage($single, $uploadsDir, $errors);
            if ($saved) {
                $newImages[] = $saved;
            }
        }
    }

    if (!$errors) {
        if (!empty($_POST['id'])) {
            $stmt = $pdo->prepare('UPDATE products SET name=?, description=?, price=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$name, $desc, $price, $_POST['id']]);
            $productId = (int)$_POST['id'];
        } else {
            $stmt = $pdo->prepare('INSERT INTO products (name, description, price) VALUES (?,?,?)');
            $stmt->execute([$name, $desc, $price]);
            $productId = (int)$pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$productId]);
        $insCat = $pdo->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (?,?)');
        foreach ($catIds as $cid) {
            if ($cid > 0) {
                $insCat->execute([$productId, $cid]);
            }
        }

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

        $sortBaseStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?');
        $sortBaseStmt->execute([$productId]);
        $sortOrder = ((int)$sortBaseStmt->fetchColumn()) + 1;
        $insImg = $pdo->prepare('INSERT INTO product_images (product_id, filename, sort_order) VALUES (?,?,?)');
        foreach ($newImages as $filename) {
            $insImg->execute([$productId, $filename, $sortOrder]);
            $sortOrder++;
        }

        $thumbStmt = $pdo->prepare('SELECT filename FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1');
        $thumbStmt->execute([$productId]);
        $thumb = $thumbStmt->fetchColumn() ?: null;
        $pdo->prepare('UPDATE products SET image = ? WHERE id = ?')->execute([$thumb, $productId]);

        header('Location: products.php');
        exit;
    }

    if (!empty($_POST['id'])) {
        $stmtImg = $pdo->prepare('SELECT id, filename FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
        $stmtImg->execute([(int)$_POST['id']]);
        $productImages = $stmtImg->fetchAll();
    }
}

$selectedCategoryIds = $old['category_ids'];
?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<div class="container">
  <h2><?php echo $product ? 'Edit' : 'Add'; ?> Product</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <strong>Please fix the following:</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <?php if ($product): ?><input type="hidden" name="id" value="<?php echo h($product['id']); ?>"><?php endif; ?>
    <div class="mb-3"><label class="form-label">Name</label><input name="name" value="<?php echo h($old['name']); ?>" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control"><?php echo h($old['description']); ?></textarea></div>
    <div class="mb-3"><label class="form-label">Price</label><input name="price" type="number" min="0" step="0.01" value="<?php echo h($old['price']); ?>" class="form-control"></div>
    <div class="mb-3"><label class="form-label">Categories (hold Ctrl to select multiple)</label>
      <select name="category_ids[]" multiple class="form-select" size="7">
        <?php foreach ($categories as $c): ?>
          <option value="<?php echo h($c['id']); ?>" <?php if (in_array((int)$c['id'], $selectedCategoryIds, true)) echo 'selected'; ?>><?php echo h($c['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (!empty($productImages)): ?>
      <div class="mb-3">
        <label class="form-label">Existing Images</label>
        <div class="row g-2">
          <?php foreach ($productImages as $img): ?>
            <div class="col-md-3 col-6">
              <div class="card p-2">
                <img src="../assets/uploads/<?php echo h($img['filename']); ?>" alt="" style="width:100%;height:110px;object-fit:cover;border-radius:6px;">
                <label class="form-check mt-2">
                  <input type="checkbox" class="form-check-input" name="remove_image_ids[]" value="<?php echo h($img['id']); ?>">
                  <span class="form-check-label small">Remove</span>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label">Add Images</label>
      <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" class="form-control" multiple>
      <small class="form-text text-muted">Upload multiple images. Max 2MB each. JPG/PNG/WEBP.</small>
    </div>

    <button class="btn btn-primary"><?php echo $product ? 'Update' : 'Create'; ?></button>
    <a class="btn btn-secondary" href="products.php">Cancel</a>
  </form>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

<?php
require_once __DIR__ . '/../functions.php';
requireAuth();
global $pdo;

// Filters
$where = [];
$params = [];
if (!empty($_GET['q'])) {
  $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
  $params[] = '%' . $_GET['q'] . '%';
  $params[] = '%' . $_GET['q'] . '%';
}
if (!empty($_GET['category_id'])) {
  $where[] = 'pc.category_id = ?';
  $params[] = (int)$_GET['category_id'];
}
if (isset($_GET['min_price']) && $_GET['min_price'] !== '') {
  $where[] = 'p.price >= ?'; $params[] = (float)$_GET['min_price'];
}
if (isset($_GET['max_price']) && $_GET['max_price'] !== '') {
  $where[] = 'p.price <= ?'; $params[] = (float)$_GET['max_price'];
}

$order = 'p.id DESC';
if (!empty($_GET['sort'])) {
  if ($_GET['sort'] === 'price_asc') $order = 'p.price ASC';
  if ($_GET['sort'] === 'price_desc') $order = 'p.price DESC';
}

$sql = 'SELECT DISTINCT p.*, 
  COALESCE((SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order, pi.id LIMIT 1), p.image) AS thumb,
  (SELECT COUNT(*) FROM product_images pi2 WHERE pi2.product_id = p.id) AS image_count
  FROM products p LEFT JOIN product_categories pc ON p.id = pc.product_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY ' . $order;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$allCats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$productIds = array_map('intval', array_column($products, 'id'));
$productCats = [];
if (!empty($productIds)) {
  $in = implode(',', array_fill(0, count($productIds), '?'));
  $stmtPc = $pdo->prepare('SELECT pc.product_id, c.name FROM product_categories pc INNER JOIN categories c ON c.id = pc.category_id WHERE pc.product_id IN (' . $in . ') ORDER BY c.name');
  $stmtPc->execute($productIds);
  foreach ($stmtPc->fetchAll() as $row) {
    $pid = (int)$row['product_id'];
    if (!isset($productCats[$pid])) {
      $productCats[$pid] = [];
    }
    $productCats[$pid][] = $row['name'];
  }
}
?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Products</h2>
    <div>
      <a href="products_export.php" class="btn btn-outline-primary">Export CSV</a>
      <a href="products_import.php" class="btn btn-outline-secondary">Import CSV</a>
      <a href="product_form.php" class="btn btn-success">Add Product</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-4"><input name="q" value="<?php echo h($_GET['q'] ?? ''); ?>" class="form-control" placeholder="Search"></div>
    <div class="col-md-3">
      <select name="category_id" class="form-select">
        <option value="">All categories</option>
        <?php foreach ($allCats as $c): ?>
          <option value="<?php echo h($c['id']); ?>" <?php if (!empty($_GET['category_id']) && $_GET['category_id']==$c['id']) echo 'selected'; ?>><?php echo h($c['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1"><input name="min_price" value="<?php echo h($_GET['min_price'] ?? ''); ?>" class="form-control" placeholder="Min"></div>
    <div class="col-md-1"><input name="max_price" value="<?php echo h($_GET['max_price'] ?? ''); ?>" class="form-control" placeholder="Max"></div>
    <div class="col-md-2">
      <select name="sort" class="form-select">
        <option value="">Sort</option>
        <option value="price_asc" <?php if(($_GET['sort'] ?? '')==='price_asc') echo 'selected'; ?>>Price ↑</option>
        <option value="price_desc" <?php if(($_GET['sort'] ?? '')==='price_desc') echo 'selected'; ?>>Price ↓</option>
      </select>
    </div>
    <div class="col-md-12 text-end"><button class="btn btn-secondary">Filter</button></div>
  </form>

  <div class="row">
    <?php foreach ($products as $p): ?>
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <?php if (!empty($p['thumb'])): ?>
            <img src="../assets/uploads/<?php echo h($p['thumb']); ?>" class="card-img-top" style="height:180px;object-fit:cover">
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title"><?php echo h($p['name']); ?></h5>
            <p class="card-text small text-muted">Price: <?php echo number_format($p['price'],2); ?></p>
            <p class="card-text small text-muted">Images: <?php echo (int)$p['image_count']; ?></p>
            <div class="mb-2">
              <?php foreach (($productCats[(int)$p['id']] ?? []) as $catName): ?>
                <span class="badge text-bg-light border"><?php echo h($catName); ?></span>
              <?php endforeach; ?>
            </div>
            <div class="mt-auto">
              <a class="btn btn-sm btn-primary" href="product_form.php?id=<?php echo $p['id']; ?>">Edit</a>
              <form method="post" action="product_delete.php" style="display:inline" onsubmit="return confirm('Delete?')">
                <input type="hidden" name="id" value="<?php echo h($p['id']); ?>">
                <button class="btn btn-sm btn-danger">Delete</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

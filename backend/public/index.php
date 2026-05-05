<?php
require_once __DIR__ . '/../functions.php';
requireAuth();
?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<div class="container">
  <div class="row">
    <div class="col-md-8">
      <h1 class="mb-3">Dashboard</h1>
      <div class="list-group">
        <a class="list-group-item list-group-item-action" href="products.php">Manage Products</a>
        <a class="list-group-item list-group-item-action" href="categories.php">Manage Categories</a>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

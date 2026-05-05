<?php
require_once __DIR__ . '/../functions.php';
requireAuth();
global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'delete' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$_POST['id']]);
    } elseif ($action === 'create' && !empty($_POST['name'])) {
        $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
        $stmt->execute([$_POST['name']]);
    } elseif ($action === 'update' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $_POST['id']]);
    }
    header('Location: categories.php'); exit;
}

$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<div class="container">
  <h2>Categories</h2>
  <form class="mb-3 d-flex" method="post">
    <input type="hidden" name="action" value="create">
    <input name="name" class="form-control me-2" placeholder="New category name">
    <button class="btn btn-success">Add</button>
  </form>
  <table class="table">
    <thead><tr><th>#</th><th>Name</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td><?php echo h($c['id']); ?></td>
          <td>
            <form method="post" class="d-flex">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?php echo h($c['id']); ?>">
              <input name="name" value="<?php echo h($c['name']); ?>" class="form-control me-2">
              <button class="btn btn-primary btn-sm me-2">Save</button>
            </form>
          </td>
          <td>
            <form method="post" onsubmit="return confirm('Delete?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo h($c['id']); ?>">
              <button class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

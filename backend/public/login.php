<?php
require_once __DIR__ . '/../functions.php';
if (isLogged()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (loginUser($user, $pass)) {
        header('Location: index.php'); exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<div class="container mt-5" style="max-width:480px">
  <div class="card">
    <div class="card-body">
      <h3 class="card-title">Admin Login</h3>
      <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
      <form method="post">
        <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Password</label><input name="password" type="password" class="form-control"></div>
        <button class="btn btn-primary">Login</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

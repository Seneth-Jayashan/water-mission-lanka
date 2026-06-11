<?php
require_once __DIR__ . '/../functions.php';
global $pdo;
global $ADMIN_USERNAME, $ADMIN_PASSWORD;

// Check if admin exists
$stmt = $pdo->query('SELECT COUNT(*) FROM admins');
$adminCount = (int) $stmt->fetchColumn();

if (isLogged()) { header('Location: index.php'); exit; }
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_admin'])) {
        if ($adminCount > 0) {
            $error = 'An admin user already exists. Only one admin account can exist.';
      } elseif (empty($ADMIN_USERNAME) || empty($ADMIN_PASSWORD)) {
        $error = 'Set ADMIN_USERNAME and ADMIN_PASSWORD in the environment before creating the admin account.';
        } else {
        $hash = password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
            try {
          $stmt->execute([$ADMIN_USERNAME, $hash]);
                $success = 'Admin created successfully. You can now login.';
                $adminCount = 1;
            } catch (Exception $e) {
                $error = 'Error: ' . h($e->getMessage());
            }
        }
    } else {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        if (loginUser($user, $pass)) {
            header('Location: index.php'); exit;
        } else {
            $error = 'Invalid credentials';
        }
    }
}
?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<div class="container mt-5" style="max-width:480px">
  <div class="card">
    <div class="card-body">
      <h3 class="card-title">Admin Login</h3>
      <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>
      
      <?php if ($adminCount === 0): ?>
        <div class="alert alert-info">
          No admin account exists. Click the button below to create one using the credentials stored in the environment variables.
        </div>
        <form method="post" class="mb-4 text-center">
            <button name="create_admin" value="1" class="btn btn-success">Create Admin Account</button>
        </form>
        <hr>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Password</label><input name="password" type="password" class="form-control"></div>
        <button class="btn btn-primary">Login</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

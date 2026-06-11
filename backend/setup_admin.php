<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
global $ADMIN_USERNAME, $ADMIN_PASSWORD;

// Create tables if missing
// Run schema (create missing tables)
$sql = file_get_contents(__DIR__ . '/init.sql');
$pdo->exec($sql);
// Ensure columns/tables for older installs are present (best-effort)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) { }
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) { }
try {
  $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS image VARCHAR(255) NULL;");
} catch (Exception $e) { /* ignore if exists or not supported */ }

$stmt = $pdo->query('SELECT COUNT(*) FROM admins');
$adminCount = (int) $stmt->fetchColumn();

if (php_sapi_name() === 'cli') {
    if ($adminCount > 0) {
        echo "An admin user already exists. Only one admin account can exist.\n";
        exit;
    }
  if (empty($ADMIN_USERNAME) || empty($ADMIN_PASSWORD)) {
    echo "Set ADMIN_USERNAME and ADMIN_PASSWORD in the environment before creating the admin user.\n";
    exit(1);
  }
    echo "Create initial admin user\n";
  $hash = password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
  $stmt->execute([$ADMIN_USERNAME, $hash]);
  echo "Created admin user from environment variables: $ADMIN_USERNAME\n";
    exit;
}

// Web flow
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($adminCount > 0) {
        $message = 'Error: An admin user already exists. Only one admin account can exist.';
  } elseif (empty($ADMIN_USERNAME) || empty($ADMIN_PASSWORD)) {
    $message = 'Error: Set ADMIN_USERNAME and ADMIN_PASSWORD in the environment before creating the admin account.';
    } else {
    $hash = password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        try {
      $stmt->execute([$ADMIN_USERNAME, $hash]);
            $message = 'Admin created successfully. <a href="public/login.php">Go to login</a>';
            $adminCount = 1;
        } catch (Exception $e) {
            $message = 'Error: ' . h($e->getMessage());
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Setup Admin</title>
</head>
<body>
  <h1>Admin Setup</h1>
  <?php if ($message): ?>
    <p><?= $message ?></p>
  <?php endif; ?>

  <?php if ($adminCount > 0): ?>
    <p>An admin account already exists. Only one admin account can exist.</p>
  <?php else: ?>
    <form method="post">
      <p>No admin account exists. Click the button below to create one using the credentials stored in the environment variables.</p>
      <button type="submit">Create Admin Account</button>
    </form>
  <?php endif; ?>
</body>
</html>

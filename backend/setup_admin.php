<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

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

if (php_sapi_name() === 'cli') {
    echo "Create initial admin user\n";
    $username = readline('Username (admin): ');
    if (trim($username) === '') $username = 'admin';
    $password = readline('Password (admin123): ');
    if (trim($password) === '') $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $hash]);
    echo "Created admin user: $username\n";
    exit;
}

// Web flow
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? 'admin';
    $password = $_POST['password'] ?? 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    try {
        $stmt->execute([$username, $hash]);
        echo 'Admin created. <a href="public/login.php">Go to login</a>';
    } catch (Exception $e) {
        echo 'Error: ' . h($e->getMessage());
    }
    exit;
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Setup Admin</title>
</head>
<body>
  <h1>Create Admin User</h1>
  <form method="post">
    <label>Username: <input name="username" value="admin"></label><br>
    <label>Password: <input name="password" value="admin123" type="password"></label><br>
    <button type="submit">Create</button>
  </form>
</body>
</html>

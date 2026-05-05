<?php
require_once __DIR__ . '/config.php';

function isLogged()
{
    return !empty($_SESSION['user_id']);
}

function requireAuth()
{
    if (!isLogged()) {
        header('Location: login.php');
        exit;
    }
}

function loginUser($username, $password)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        return true;
    }
    return false;
}

function logoutUser()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

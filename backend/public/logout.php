<?php
require_once __DIR__ . '/../functions.php';
logoutUser();
header('Location: login.php');
exit;

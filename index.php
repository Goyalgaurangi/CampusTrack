<?php
require_once __DIR__ . '/config/auth.php';

$user = current_user();
if (!$user) {
    header('Location: auth/login.php');
    exit;
}

$home = match ($user['role']) {
    'admin' => 'admin/index.php',
    'faculty' => 'faculty/index.php',
    default => 'student/index.php',
};
header('Location: ' . $home);
exit;
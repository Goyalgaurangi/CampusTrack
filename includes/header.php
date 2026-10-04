<?php
require_once __DIR__ . '/functions.php';
$user = current_user();
$title = $pageTitle ?? 'CampusTrack';
$nav = [];
if ($user) {
    $prefix = match ($user['role']) {
        'admin' => '../admin/index.php',
        'faculty' => '../faculty/index.php',
        default => '../student/index.php',
    };
    $nav = match ($user['role']) {
        'admin' => [
            ['Dashboard', 'dashboard'], ['Courses', 'courses'], ['Users', 'users'], ['Assignments', 'assignments'],
        ],
        'faculty' => [
            ['Dashboard', 'dashboard'], ['Attendance', 'attendance'], ['Grades', 'grades'], ['Materials', 'materials'],
        ],
        default => [
            ['Dashboard', 'dashboard'], ['Attendance', 'attendance'], ['Schedule', 'schedule'], ['Grades', 'grades'], ['Materials', 'materials'], ['Report card', 'report'],
        ],
    };
}
$flashMessage = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102438">
    <title><?= e($title) ?> · CampusTrack</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script defer src="../assets/js/script.js"></script>
</head>
<body>
<?php if ($user): ?>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="<?= e($prefix) ?>"><span class="brand-mark">C</span><span>Campus<span class="brand-light">Track</span></span></a>
        <div class="sidebar-label">Workspace</div>
        <nav class="side-nav" aria-label="Main navigation">
            <?php foreach ($nav as [$label, $navView]): ?>
                <a class="<?= (($_GET['view'] ?? 'dashboard') === $navView ? 'active' : '') ?>" href="<?= e($prefix) ?>?view=<?= e($navView) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="profile-chip"><span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span><span><strong><?= e($user['name']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></span></div>
            <form action="../auth/logout.php" method="post"><?= csrf_field() ?><button class="logout-link" type="submit">Sign out</button></form>
        </div>
    </aside>
    <main class="main-area">
        <header class="topbar"><span class="eyebrow"><?= e(ucfirst($user['role'])) ?> portal</span><div class="topbar-actions"><span class="topbar-date"><?= e(date('l, F j, Y')) ?></span><form action="../auth/logout.php" method="post" class="topbar-logout"><?= csrf_field() ?><button class="logout-button" type="submit">Sign out</button></form></div></header>
        <div class="page-content">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= $flashMessage['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible" role="alert">
                    <?= e($flashMessage['message']) ?><button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
<?php else: ?>
<main class="login-page">
<?php endif; ?>
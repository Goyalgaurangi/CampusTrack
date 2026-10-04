<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'campustrack';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '3306';
$message = '';
$success = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException('The database name must contain only letters, numbers, or underscores.');
        }
        $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $hasUsersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($hasUsersTable && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            $success = true;
            $message = 'CampusTrack already contains user accounts. No database records were changed. Remove setup.php from the project after setup.';
        } else {
            $sql = file_get_contents(__DIR__ . '/database/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('database/schema.sql could not be read.');
            }
            $sql = preg_replace('/^\s*CREATE DATABASE IF NOT EXISTS campustrack[^;]*;\s*USE campustrack;\s*/i', '', $sql, 1);
            $statements = array_filter(array_map('trim', explode(';', (string) $sql)));
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
            $success = true;
            $message = 'CampusTrack is set up with demo courses, schedules, student enrollments, attendance, and grades. Remove setup.php from the project after setup.';
        }
    } catch (Throwable $exception) {
        error_log('CampusTrack setup failed: ' . $exception->getMessage());
        $message = 'Setup did not finish. Confirm MySQL is running, the selected account can create databases, and check the PHP error log.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CampusTrack setup</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="setup-page">
    <main class="setup-card">
        <a class="brand login-brand" href="index.php"><span class="brand-mark">C</span><span>Campus<span class="brand-light">Track</span></span></a>
        <p class="eyebrow">ONE-TIME INSTALLER</p>
        <h1>Set up CampusTrack</h1>
        <p class="muted">This creates the MySQL tables and fills a new database with the demo academic records.</p>
        <?php if ($message !== ''): ?><div class="alert alert-<?= $success ? 'success' : 'danger' ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        <form method="post"><button class="button button-primary button-wide" type="submit">Create database tables and demo data</button></form>
        <p class="login-note">This does not erase existing user data. For a new install, you can also import <code>database/schema.sql</code> with phpMyAdmin.</p>
        <?php if ($success): ?><a class="button button-quiet button-wide" href="auth/login.php">Go to sign in</a><?php endif; ?>
    </main>
</body>
</html>
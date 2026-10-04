<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (current_user()) {
    go('../index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(post_string('email'));
    $password = (string) ($_POST['password'] ?? '');
    if (!is_valid_email($email) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $query = db()->prepare('SELECT id, name, email, password_hash, role, department, identifier FROM users WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        $record = $query->fetch();
        if ($record && password_verify($password, $record['password_hash'])) {
            session_regenerate_id(true);
            unset($record['password_hash']);
            $_SESSION['user'] = $record;
            go('../index.php');
        }
        $error = 'That email and password combination was not found.';
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/../includes/header.php';
?>
<section class="login-card">
    <a class="brand login-brand" href="../index.php"><span class="brand-mark">C</span><span>Campus<span class="brand-light">Track</span></span></a>
    <p class="eyebrow">ACADEMIC PORTAL</p>
    <h1>Welcome back</h1>
    <p class="muted">Sign in to continue to your campus workspace.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack-form" data-validate>
        <?= csrf_field() ?>
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="username" required maxlength="190" value="<?= e($_POST['email'] ?? '') ?>">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="button button-primary button-wide" type="submit">Sign in <span aria-hidden="true">→</span></button>
    </form>
    <div class="demo-credentials">
        <strong>Demo accounts</strong>
        <span>Admin · admin@campustrack.com · <code>admin123</code></span>
        <span>Faculty · faculty1@campustrack.com · <code>faculty123</code></span>
        <span>Student · student1@campustrack.com · <code>student123</code></span>
    </div>
    <p class="login-note">Using these accounts? Change their passwords before using real student data.</p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/config/env.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php';
if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) $redirect = 'index.php';

if (!empty($_SESSION['authenticated'])) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $valid_user = getenv('AUTH_USERNAME') ?: '';
    $valid_hash = getenv('AUTH_PASSWORD_HASH') ?: '';

    $attempts = $_SESSION['login_attempts'] ?? 0;

    if ($valid_user === '' || $valid_hash === '') {
        $error = 'Login is not configured yet. Contact the administrator.';
    } elseif ($attempts >= 5) {
        sleep(2);
        $error = 'Too many failed attempts. Please wait a moment and try again.';
    } elseif (hash_equals($valid_user, $username) && password_verify($password, $valid_hash)) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['username']      = $username;
        unset($_SESSION['login_attempts']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $_SESSION['login_attempts'] = $attempts + 1;
        sleep(1);
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CrabKids Dashboard — Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="login-wrapper">
  <div class="login-card">

    <div class="login-header">
      <img src="assets/images/logo.svg" alt="CrabKids logo" class="logo">
      <h4 class="brand-name">CrabKids Kenya</h4>
      <p class="brand-sub">Stock Dashboard</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="mb-3">
        <label for="username" class="form-label fw-semibold">Username</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
          <input
            type="text"
            id="username"
            name="username"
            class="form-control"
            placeholder="Enter username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autocomplete="username"
            required
            autofocus
          >
        </div>
      </div>

      <div class="mb-4">
        <label for="password" class="form-label fw-semibold">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
          <input
            type="password"
            id="password"
            name="password"
            class="form-control"
            placeholder="Enter password"
            autocomplete="current-password"
            required
          >
          <button class="btn btn-outline-secondary toggle-pw" type="button" tabindex="-1" aria-label="Toggle password visibility">
            <i class="bi bi-eye-fill"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 login-btn">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>

    <p class="login-footer">
      &copy; <?= date('Y') ?> CrabKids Kenya. All rights reserved.
    </p>

  </div>
</div>

<script>
  document.querySelector('.toggle-pw').addEventListener('click', function () {
    var pw = document.getElementById('password');
    var icon = this.querySelector('i');
    if (pw.type === 'password') {
      pw.type = 'text';
      icon.classList.replace('bi-eye-fill', 'bi-eye-slash-fill');
    } else {
      pw.type = 'password';
      icon.classList.replace('bi-eye-slash-fill', 'bi-eye-fill');
    }
  });
</script>

</body>
</html>

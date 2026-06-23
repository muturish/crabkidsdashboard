<?php
session_start();

// Credentials
define('VALID_USERNAME', 'crabkids');
define('VALID_PASSWORD', 'Njukush1@2');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === VALID_USERNAME && $password === VALID_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

// Already logged in
if (!empty($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
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
            <img src="assets/images/logo.avif" alt="CrabKids Logo" class="logo">
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
        const pw = document.getElementById('password');
        const icon = this.querySelector('i');
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

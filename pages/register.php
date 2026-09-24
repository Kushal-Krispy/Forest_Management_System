<?php
require_once __DIR__ . '/../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . url('pages/dashboard.php'));
    exit;
}

$error = $_SESSION['flash_error'] ?? null;
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('fms_theme');
                var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <title>Register | <?= sanitize(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
</head>
<body>
    <button class="auth-theme-btn" id="authThemeToggle" title="Toggle theme">
        <i class="fas fa-moon"></i>
    </button>

    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <i class="fas fa-user-plus"></i>
                    <h1>Create Account</h1>
                    <p>Public registration. Officers are assigned by admin.</p>
                </div>

                <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="flash flash-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <form action="<?= url('actions/register_action.php') ?>" method="POST">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required minlength="3">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-input-wrap">
                            <input type="password" id="password" name="password" required minlength="8">
                            <button type="button" class="password-toggle-btn" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-input-wrap">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="password-toggle-btn" data-toggle-password="confirm_password" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary auth-btn">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </form>

                <div class="auth-footer">
                    Already registered?
                    <a href="<?= url('pages/login.php') ?>">Sign in</a>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset('js/theme.js') ?>"></script>
    <script src="<?= asset('js/password-toggle.js') ?>"></script>
</body>
</html>

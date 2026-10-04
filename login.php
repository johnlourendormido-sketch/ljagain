<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
start_session();

if (is_logged_in()) {
    $role = $_SESSION['role'] ?? 'tourist';
    if ($role === 'admin') {
        header("Location: " . BASE_URL . "/admin/index.php");
    } else {
        header("Location: " . BASE_URL . "/{$role}/index.php");
    }
    exit;
}

$is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
$errors = [];
$email = '';

if (is_post()) {
    if (!verify_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = 'Email and password are required.';
        } else {
            $result = login($email, $password);
            if ($result['success']) {
                $user_role = $result['user']['role'];
                if ($user_role !== 'admin') {
                    $errors[] = 'This login is for admin accounts only.';
                    $email = '';
                } else {
                    if (!empty($_POST['remember'])) {
                        $p = session_get_cookie_params();
                        setcookie(session_name(), session_id(), time() + 60 * 60 * 24 * 30, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                    }
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => true, 'redirect' => BASE_URL . "/admin/index.php"]);
                        exit;
                    }
                    header("Location: " . BASE_URL . "/admin/index.php");
                    exit;
                }
            } else {
                $errors[] = $result['message'];
            }
        }
    }
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $errors[0] ?? 'Unable to sign you in.', 'errors' => $errors]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <title>Admin Login | BINALGO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css?v=20260815" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/auth.css?v=20260815" rel="stylesheet">
    <style>
        .auth-split-left { background: linear-gradient(135deg, #0c6e5e 0%, #0a5c4f 50%, #084a40 100%) !important; }
        .auth-split-logo { background: rgba(255,255,255,0.15) !important; color: #fff !important; }
        .auth-split-portal { color: rgba(255,255,255,0.7) !important; }
        .auth-split-brand { color: #fff !important; }
        .auth-split-tagline { color: rgba(255,255,255,0.85) !important; }
        .auth-split-address { color: rgba(255,255,255,0.5) !important; }
        .auth-role-badge { display: inline-flex; align-items: center; gap: 6px; background: #0c6e5e; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; margin-bottom: 8px; }
        .auth-role-badge i { font-size: 0.7rem; }
    </style>
</head>
<body>
<div class="auth-split">
    <div class="auth-split-blobs">
        <span class="sblob-1"></span>
        <span class="sblob-2"></span>
        <span class="sblob-3"></span>
        <span class="sblob-4"></span>
    </div>
    <div class="auth-split-card">
        <aside class="auth-split-left">
            <div class="split-ring"></div>
            <div class="split-ring-2"></div>
            <div class="auth-split-logo"><i class="fas fa-shield-halved"></i></div>
            <div class="auth-split-portal">Admin Portal</div>
            <p class="auth-split-tagline">" Manage and oversee tourism operations "</p>
            <div class="auth-split-brand">BINALGO Tourism</div>
            <div class="auth-split-address">Binalbagan, Negros Occidental, Philippines</div>
        </aside>
        <section class="auth-split-right">
            <div class="auth-split-greeting">
                <span class="auth-split-greeting-icon"><i class="fas fa-user-shield"></i></span>
                <div>
                    <div class="auth-role-badge"><i class="fas fa-crown"></i> Administrator</div>
                    <h1 class="auth-split-welcome">Admin Login</h1>
                    <p class="auth-split-subtitle">Sign in to the admin dashboard</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="auth-banner error show" role="alert">
                    <i class="auth-banner-icon fas fa-circle-exclamation"></i>
                    <span class="auth-banner-text"><?= sanitize($errors[0]) ?></span>
                    <button type="button" class="auth-banner-close" aria-label="Dismiss"><i class="fas fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <div class="auth-banner error" id="loginBanner" role="alert" aria-live="polite">
                <i class="auth-banner-icon fas fa-circle-exclamation"></i>
                <span class="auth-banner-text"></span>
                <button type="button" class="auth-banner-close" aria-label="Dismiss"><i class="fas fa-xmark"></i></button>
            </div>

            <form method="POST" action="" id="loginForm" novalidate>
                <?= csrf_field() ?>

                <div class="auth-input-group">
                    <label for="email" class="auth-input-label"><i class="fas fa-envelope"></i>Email Address</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="auth-input" id="email" name="email"
                               value="<?= sanitize($email) ?>" placeholder="admin@binalgo.com" autocomplete="email">
                        <span class="auth-validity" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
                    </div>
                    <div class="auth-field-msg" id="emailMsg" aria-live="polite">
                        <i class="fas fa-circle-exclamation"></i><span></span>
                    </div>
                </div>

                <div class="auth-input-group">
                    <label for="password" class="auth-input-label"><i class="fas fa-lock"></i>Password</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" class="auth-input has-toggle" id="password" name="password"
                               placeholder="Enter your password" autocomplete="current-password">
                        <button class="auth-input-action" type="button" id="togglePassword" tabindex="-1" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye"></i>
                        </button>
                        <span class="auth-validity" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
                    </div>
                    <div class="d-flex justify-content-end">
                        <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="auth-forgot">Forgot password?</a>
                    </div>
                    <div class="auth-field-msg" id="passwordMsg" aria-live="polite">
                        <i class="fas fa-circle-exclamation"></i><span></span>
                    </div>
                </div>

                <div class="auth-remember">
                    <label class="auth-check">
                        <input type="checkbox" id="remember" name="remember">
                        <span class="auth-check-mark"><i class="fas fa-check"></i></span>
                        <span class="auth-check-text">Remember me for 30 days</span>
                    </label>
                </div>

                <button type="submit" class="auth-submit-btn auth-ripple" id="loginBtn">
                    <span class="auth-submit-text"><i class="fas fa-arrow-right-to-bracket"></i> Sign In as Admin</span>
                    <span class="auth-submit-loading"><span class="auth-spinner"></span> Signing in...</span>
                </button>
            </form>

            <div class="auth-split-actions">
                <a href="<?= BASE_URL ?>/auth/login.php" class="auth-split-action"><i class="fas fa-right-to-bracket"></i> General Login</a>
                <a href="<?= BASE_URL ?>/" class="auth-split-action auth-split-action--solid"><i class="fas fa-home"></i> Back to Home</a>
            </div>

            <div class="auth-split-security">
                <span><i class="fas fa-shield-halved"></i> Admin Access Only</span>
                <span><i class="fas fa-lock"></i> Encrypted</span>
            </div>

            <div class="auth-split-powered">Powered by <b>BINALGO</b> Tourism</div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/auth.js"></script>
<script>
(function () {
    var BASE = (document.querySelector('meta[name="base-url"]') || {}).content || '';
    var form = document.getElementById('loginForm');
    var banner = document.getElementById('loginBanner');
    var btn = document.getElementById('loginBtn');
    var emailInput = document.getElementById('email');
    var passwordInput = document.getElementById('password');

    function msgFor(id) { return document.getElementById(id); }

    var checkEmail = function () {
        var v = emailInput.value.trim();
        if (!v) { Auth.clearFieldState(emailInput); return false; }
        var ok = Auth.isValidEmail(v);
        Auth.setFieldState(emailInput, ok, { msgEl: msgFor('emailMsg'), badText: 'Please enter a valid email address.' });
        return ok;
    };
    emailInput.addEventListener('input', Auth.debounce(checkEmail, 250));
    emailInput.addEventListener('blur', checkEmail);

    var checkPassword = function () {
        var v = passwordInput.value;
        if (!v) { Auth.clearFieldState(passwordInput); return false; }
        Auth.setFieldState(passwordInput, true, { msgEl: msgFor('passwordMsg'), badText: 'Password is required.' });
        return true;
    };
    passwordInput.addEventListener('input', Auth.debounce(checkPassword, 250));
    passwordInput.addEventListener('blur', checkPassword);

    Auth.setupPasswordToggle(passwordInput, document.getElementById('togglePassword'));

    document.querySelectorAll('.auth-banner .auth-banner-close').forEach(function (b) {
        b.addEventListener('click', function () { Auth.hideBanner(banner); });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        Auth.hideBanner(banner);
        if (!checkEmail() || !checkPassword()) {
            form.classList.remove('auth-shake'); void form.offsetWidth; form.classList.add('auth-shake');
            Auth.focusInvalid(form); return;
        }
        Auth.setButtonLoading(btn, true);
        var fd = new FormData(form);
        fetch(BASE + '/admin/login.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            var ct = res.headers.get('content-type') || '';
            if (ct.indexOf('application/json') !== -1) return res.json();
            return Promise.resolve({ ok: false, message: 'Session active. Redirecting...', redirect: res.url });
        }).then(function (data) {
            if (data && data.ok && data.redirect) { window.location.assign(data.redirect); }
            else { Auth.showBanner(banner, (data && data.message) || 'Sign-in failed.'); Auth.setButtonLoading(btn, false); }
        }).catch(function () {
            Auth.showBanner(banner, 'Unable to reach the server.'); Auth.setButtonLoading(btn, false);
        });
    });
})();
</script>
</body>
</html>

<?php
// views/auth/login.php
// Figma: "Login - Sports Tournament Management System.png"

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

$error   = $_SESSION['login_error']   ?? '';
$success = $_SESSION['signup_success'] ?? '';
unset($_SESSION['login_error'], $_SESSION['signup_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Sports Tournament Management System</title>
    <meta name="description" content="Login to STMS — University Sports Portal for Admins, Coaches, Staff and Spectators.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/forms.css">
</head>
<body>

<div class="auth-page">
    <div style="width:100%;max-width:460px;">

        <!-- Card -->
        <div class="auth-card">

            <!-- Logo -->
            <div class="auth-logo">
                <h1>Sports Tournament<br>Management System</h1>
                <p>University Sports Portal</p>
            </div>

            <!-- Success Message -->
            <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom:16px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="<?= BASE_URL ?>controllers/AuthController.php?action=login" id="loginForm">

                <!-- Username -->
                <div class="form-group">
                    <label class="input-label" for="username">Username / Email</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Enter your username"
                        autocomplete="off"
                        required
                    >
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="input-label" for="password">Password</label>
                    <div class="input-group" style="position:relative;">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="••••••••"
                            required
                        >
                        <button type="button" onclick="togglePassword()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">
                            <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Sign In Button -->
                <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:12px;text-transform:uppercase;letter-spacing:0.08em;">
                    Sign In
                </button>

            </form>

            <!-- Divider -->
            <div class="divider" style="margin:20px 0 14px;"></div>

            <!-- Footer Links -->
            <div style="display:flex;justify-content:center;align-items:center;">
                <span style="font-size:0.82rem;color:var(--text-muted);display:flex;align-items:center;gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Secure System Access
                </span>
            </div>

        </div>

        <!-- Signup link -->
        <div class="auth-link-row" style="margin-top:20px;">
            New spectator? <a href="<?= BASE_URL ?>views/auth/signup.php">Create an account</a>
        </div>

        <!-- Copyright -->
        <p style="text-align:center;font-size:0.75rem;color:var(--text-muted);margin-top:20px;">
            © <?= date('Y') ?> Sports Tournament Management System. All rights reserved.
        </p>

    </div>
</div>

<script>
function togglePassword() {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>

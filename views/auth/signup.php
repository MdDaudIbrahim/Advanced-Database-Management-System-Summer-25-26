<?php
// views/auth/signup.php
// Figma: "Sign Up - STMS Public Portal.png"

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

$error = $_SESSION['signup_error'] ?? '';
unset($_SESSION['signup_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — STMS Public Portal</title>
    <meta name="description" content="Create a spectator account to browse tournaments and buy tickets on STMS.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/forms.css">
</head>
<body style="background:var(--bg-main);">

<!-- Navbar -->
<nav style="display:flex;align-items:center;justify-content:space-between;padding:14px 32px;border-bottom:1px solid var(--border);background:var(--bg-card);">
    <span style="font-size:1rem;font-weight:800;color:var(--primary);">Sports Tournament Management System</span>
    <a href="<?= BASE_URL ?>views/auth/login.php" class="btn btn-outline btn-sm">Login</a>
</nav>

<div class="auth-page" style="padding-top:40px;">
    <div style="width:100%;max-width:460px;">

        <div class="auth-card">

            <div class="auth-logo">
                <h1 style="font-size:1.4rem;">Create a Spectator Account</h1>
                <p style="text-transform:none;letter-spacing:0;font-size:0.875rem;margin-top:6px;color:var(--text-muted);">
                    Register to follow matches, view live brackets, and track statistics.
                </p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>controllers/AuthController.php?action=signup" id="signupForm">

                <!-- Full Name -->
                <div class="form-group">
                    <label class="input-label" for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" class="form-control" placeholder="Daud Ibrahim" required>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="input-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="ur.daudibrahim@gmail.com" required>
                </div>

                <!-- Mobile -->
                <div class="form-group">
                    <label class="input-label" for="phone">Mobile Number</label>
                    <div style="display:flex;gap:0;">
                        <span style="display:flex;align-items:center;padding:10px 12px;background:var(--bg-table-header);border:1px solid var(--border);border-right:0;border-radius:var(--radius) 0 0 var(--radius);font-size:0.875rem;color:var(--text-secondary);font-weight:600;">+880</span>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="01600004544" style="border-radius:0 var(--radius) var(--radius) 0;" required>
                    </div>
                </div>

                <!-- Password Row -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="input-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label class="input-label" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <!-- Terms -->
                <div class="form-group">
                    <div class="checkbox-wrap">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms" style="font-size:0.82rem;text-transform:none;letter-spacing:0;color:var(--text-secondary);cursor:pointer;">
                            I agree to the <a href="#">Terms &amp; Conditions</a> and Privacy Policy.
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">Sign Up</button>

            </form>

            <!-- Login link -->
            <div class="auth-link-row">
                Already have an account? <a href="<?= BASE_URL ?>views/auth/login.php">Log In</a>
            </div>

            <!-- Staff/Admin notice -->
            <div class="notice-box" style="margin-top:20px;">
                <strong>Are you a Coach, Admin, or Data Entry Staff?</strong>
                Please contact the tournament organizer to receive your account.
            </div>

        </div>

    </div>
</div>

<!-- Footer -->
<footer style="text-align:center;padding:20px;font-size:0.78rem;color:var(--text-muted);">
    <div style="margin-bottom:8px;">
        <a href="#" style="margin:0 12px;color:var(--text-muted);">Spectator Help</a>
        <a href="#" style="margin:0 12px;color:var(--text-muted);">Organizer Help</a>
        <a href="#" style="margin:0 12px;color:var(--text-muted);">Player Help</a>
        <a href="#" style="margin:0 12px;color:var(--text-muted);">Terms of Service</a>
    </div>
    © <?= date('Y') ?> Sports Tournament Management System. All rights reserved.
</footer>

<script>
// Client-side password match validation
document.getElementById('signupForm').addEventListener('submit', function(e) {
    const pw  = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;
    if (pw !== cpw) {
        e.preventDefault();
        document.getElementById('confirm_password').classList.add('error');
        alert('Passwords do not match!');
    }
});
</script>

</body>
</html>

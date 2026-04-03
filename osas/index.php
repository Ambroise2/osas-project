<?php
require_once 'config.php';

// Already logged in? Redirect
if (isLoggedIn()) {
    redirect($_SESSION['role'] === 'admin' ? '/admin/dashboard.php' : '/apply.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $email;
                $_SESSION['role']      = $user['role'];

                flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect($user['role'] === 'admin' ? '/admin/dashboard.php' : '/apply.php');
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — OSAS | Kabarak University</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <div class="auth-icon">KU</div>
            <h1>Student Admission Portal</h1>
            <p>Kabarak University — Online Admission System</p>
        </div>

        <div class="auth-body">
            <h2 style="font-size:1.15rem;font-weight:700;margin-bottom:1.2rem;color:var(--primary);">
                Sign in to your account
            </h2>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           placeholder="you@example.com"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:0.5rem;">
                    🔐 Sign In
                </button>
            </form>
        </div>

        <div class="divider"></div>

        <div class="auth-footer">
            Don't have an account? 
            <a href="/register.php">Create one here</a>
        </div>

        <div class="auth-footer" style="padding-top:0;font-size:0.75rem;color:#aaa;padding-bottom:1.5rem;">
            <strong>Demo:</strong> admin@osas.ac.ke / Admin@123 &nbsp;|&nbsp; student@test.com / Test@123
        </div>
    </div>

    <script>
    // Show password toggle
    document.querySelector('#password').addEventListener('dblclick', function() {
        this.type = this.type === 'password' ? 'text' : 'password';
    });
    </script>
</body>
</html>

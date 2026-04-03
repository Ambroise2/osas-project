<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect($_SESSION['role'] === 'admin' ? '/admin/dashboard.php' : '/apply.php');
}

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => sanitize($_POST['full_name'] ?? ''),
        'email'     => sanitize($_POST['email'] ?? ''),
        'phone'     => sanitize($_POST['phone'] ?? ''),
        'password'  => $_POST['password'] ?? '',
        'confirm'   => $_POST['confirm'] ?? '',
    ];

    if (empty($data['full_name']))   $errors[] = 'Full name is required.';
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                                      $errors[] = 'A valid email address is required.';
    if (empty($data['phone']))        $errors[] = 'Phone number is required.';
    if (strlen($data['password']) < 6)$errors[] = 'Password must be at least 6 characters.';
    if ($data['password'] !== $data['confirm']) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // Check if email exists
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $chk->bind_param('s', $data['email']);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $chk->close();
    }

    if (empty($errors)) {
        $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare(
            "INSERT INTO users (full_name, email, phone, password, role) VALUES (?,?,?,'student','student')"
        );
        // Fix: role is hardcoded, adjust query
        $stmt->close();

        $stmt2 = $conn->prepare(
            "INSERT INTO users (full_name, email, phone, password) VALUES (?,?,?,?)"
        );
        $stmt2->bind_param('ssss', $data['full_name'], $data['email'], $data['phone'], $hashed);

        if ($stmt2->execute()) {
            flash('success', 'Account created successfully! Please log in.');
            redirect('/index.php');
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
        $stmt2->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — OSAS | Kabarak University</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container" style="max-width:500px;">
        <div class="auth-header">
            <div class="auth-icon">KU</div>
            <h1>Create an Account</h1>
            <p>Join the Kabarak University Admission Portal</p>
        </div>

        <div class="auth-body">
            <?php if ($errors): ?>
                <div class="alert alert-error">
                    ❌ <strong>Please fix the following:</strong><br>
                    <ul style="margin:6px 0 0 16px;">
                        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="e.g. Ruth Jebet"
                           value="<?= h($data['full_name'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="you@example.com"
                               value="<?= h($data['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" placeholder="07XXXXXXXX"
                               value="<?= h($data['phone'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Min. 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm" placeholder="Repeat password" required>
                    </div>
                </div>

                <p style="font-size:0.8rem;color:var(--muted);margin-bottom:1rem;">
                    By registering, you confirm that the information you provide is accurate
                    and will be used for admission purposes only.
                </p>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    📝 Create Account
                </button>
            </form>
        </div>

        <div class="auth-footer">
            Already have an account? <a href="/index.php">Sign in here</a>
        </div>
    </div>

    <script>
    // Password strength indicator
    const pwd = document.querySelector('input[name="password"]');
    const con = document.querySelector('input[name="confirm"]');
    con.addEventListener('input', () => {
        con.style.borderColor = con.value === pwd.value ? '#1a9c6e' : '#c0392b';
    });
    </script>
</body>
</html>

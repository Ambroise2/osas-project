<?php
require_once '../config.php';
requireLogin('admin');
$action = $_POST['action'] ?? '';
if ($action === 'change_password') {
    $new = $_POST['new_password'] ?? '';
    $con = $_POST['confirm_password'] ?? '';
    if (strlen($new) < 6) { flash('error', 'Password must be at least 6 characters.'); }
    elseif ($new !== $con) { flash('error', 'Passwords do not match.'); }
    else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $s = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $s->bind_param('si', $hash, $_SESSION['user_id']);
        $s->execute(); $s->close();
        flash('success', 'Password updated successfully!');
    }
}
if ($action === 'update_email') {
    $email = sanitize($_POST['admin_email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $s = $conn->prepare("UPDATE users SET email=? WHERE id=?");
        $s->bind_param('si', $email, $_SESSION['user_id']);
        $s->execute(); $s->close();
        $_SESSION['email'] = $email;
        flash('success', 'Email updated successfully!');
    } else { flash('error', 'Invalid email address.'); }
}
redirect('/admin/dashboard.php?page=settings');

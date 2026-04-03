<?php
require_once '../config.php';
requireLogin('admin');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect('/admin/dashboard.php');

// Fetch application + user details
$stmt = $conn->prepare("
    SELECT a.*, u.full_name, u.email, u.phone
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    flash('error', 'Application not found.');
    redirect('/admin/dashboard.php');
}

// Fetch documents
$ds   = $conn->prepare("SELECT * FROM documents WHERE application_id = ?");
$ds->bind_param('i', $id);
$ds->execute();
$docs = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
$ds->close();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = in_array($_POST['new_status'] ?? '', ['Approved','Rejected','Pending'])
                 ? $_POST['new_status'] : 'Pending';
    $comment   = sanitize($_POST['admin_comment'] ?? '');

    $upd = $conn->prepare("UPDATE applications SET status=?, admin_comment=? WHERE id=?");
    $upd->bind_param('ssi', $newStatus, $comment, $id);
    if ($upd->execute()) {
        flash('success', "Application status updated to $newStatus.");
        $app['status']        = $newStatus;
        $app['admin_comment'] = $comment;
    } else {
        flash('error', 'Update failed.');
    }
    $upd->close();
    redirect('/admin/view.php?id=' . $id);
}

$badges = [
    'Pending'  => 'badge-pending',
    'Approved' => 'badge-approved',
    'Rejected' => 'badge-rejected',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application #<?= $id ?> — OSAS Admin</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<header class="topbar">
    <a href="/admin/dashboard.php" class="topbar-brand">
        <div class="topbar-logo">KU</div>
        <div>
            <div class="topbar-title">OSAS Admin</div>
            <div class="topbar-subtitle">Kabarak University</div>
        </div>
    </a>
    <nav class="topbar-nav">
        <div class="topbar-user">
            <div class="topbar-avatar"><?= initials($_SESSION['full_name']) ?></div>
            Administrator
        </div>
        <a href="/admin/dashboard.php">← Dashboard</a>
        <a href="/logout.php" class="btn-logout">Sign Out</a>
    </nav>
</header>

<main class="main" style="max-width:960px;">
    <?php showFlash(); ?>

    <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <div class="breadcrumb"><a href="/admin/dashboard.php">Dashboard</a> &rsaquo; Application #<?= $id ?></div>
            <h2>📄 Application #<?= $id ?> — <?= h($app['first_name'] . ' ' . $app['last_name']) ?></h2>
            <p>Submitted: <?= date('d M Y, g:i A', strtotime($app['submitted_at'])) ?></p>
        </div>
        <div>
            <span class="badge <?= $badges[$app['status']] ?>" style="font-size:0.9rem;padding:6px 18px;">
                <?= $app['status'] ?>
            </span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;">
        <!-- LEFT: Application Details -->
        <div>
            <!-- Personal -->
            <div class="card" style="margin-bottom:1.2rem;">
                <div class="card-header"><h3>👤 Personal Information</h3></div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value"><?= h($app['first_name'] . ' ' . $app['last_name']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Date of Birth</div>
                            <div class="detail-value"><?= date('d M Y', strtotime($app['dob'])) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Gender</div>
                            <div class="detail-value"><?= h($app['gender']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Nationality</div>
                            <div class="detail-value"><?= h($app['nationality']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">ID / BC Number</div>
                            <div class="detail-value"><?= h($app['id_number'] ?: '—') ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Address</div>
                            <div class="detail-value"><?= h($app['address'] ?: '—') ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value"><?= h($app['email']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value"><?= h($app['phone']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic -->
            <div class="card" style="margin-bottom:1.2rem;">
                <div class="card-header"><h3>🎓 Academic & Programme</h3></div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Secondary School</div>
                            <div class="detail-value"><?= h($app['school_name']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">KCSE Year</div>
                            <div class="detail-value"><?= h($app['kcse_year']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">KCSE Grade</div>
                            <div class="detail-value">
                                <strong style="font-size:1.3rem;color:var(--primary);"><?= h($app['kcse_grade']) ?></strong>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Programme</div>
                            <div class="detail-value"><?= h($app['program']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Intake</div>
                            <div class="detail-value"><?= h($app['intake']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guardian -->
            <?php if ($app['guardian_name']): ?>
            <div class="card" style="margin-bottom:1.2rem;">
                <div class="card-header"><h3>👨‍👩‍👦 Guardian</h3></div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Name</div>
                            <div class="detail-value"><?= h($app['guardian_name']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value"><?= h($app['guardian_phone']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Relationship</div>
                            <div class="detail-value"><?= h($app['guardian_rel']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documents -->
            <div class="card">
                <div class="card-header"><h3>📎 Documents</h3></div>
                <div class="card-body">
                    <?php if ($docs): ?>
                        <?php foreach ($docs as $d): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:8px;margin-bottom:8px;">
                            <span style="font-size:1.3rem;">📄</span>
                            <div>
                                <div style="font-weight:600;font-size:0.88rem;"><?= h($d['doc_type']) ?></div>
                                <div style="font-size:0.78rem;color:var(--muted);"><?= h($d['file_name']) ?></div>
                            </div>
                            <a href="/uploads/<?= h($d['file_path']) ?>" target="_blank"
                               class="btn btn-outline btn-sm" style="margin-left:auto;">View</a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--muted);font-size:0.88rem;">No documents uploaded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT: Decision Panel -->
        <div>
            <div class="card" style="position:sticky;top:80px;">
                <div class="card-header" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;">
                    <h3 style="color:white;">⚖️ Decision Panel</h3>
                </div>
                <div class="card-body">
                    <p style="font-size:0.85rem;color:var(--muted);margin-bottom:1rem;">
                        Current status: <span class="badge <?= $badges[$app['status']] ?>"><?= $app['status'] ?></span>
                    </p>

                    <form method="POST" action="">
    <div class="form-group">
        <label>Current Status</label>
        <div style="margin-bottom:1rem;">
            <span class="badge <?= $badges[$app['status']] ?>" style="font-size:1rem;padding:8px 20px;">
                <?= $app['status'] ?>
            </span>
        </div>
    </div>

    <div class="form-group">
        <label>Comment to Applicant</label>
        <textarea name="admin_comment" rows="4"
                  placeholder="Optional: Add a note for the applicant…"><?= h($app['admin_comment']) ?></textarea>
    </div>

    <?php if ($app['status'] === 'Pending'): ?>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <button type="submit" name="new_status" value="Approved" class="btn btn-success">
                ✅ Approve Application
            </button>
            <button type="submit" name="new_status" value="Rejected" class="btn btn-danger">
                ❌ Reject Application
            </button>
        </div>

    <?php elseif ($app['status'] === 'Approved'): ?>
        <div class="alert alert-success">✅ This application has been <strong>Approved</strong>.</div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <button type="submit" name="new_status" value="Rejected" class="btn btn-danger">
                ❌ Revoke & Reject
            </button>
            <button type="submit" name="new_status" value="Approved" class="btn btn-outline">
                💾 Update Comment Only
            </button>
        </div>

    <?php elseif ($app['status'] === 'Rejected'): ?>
        <div class="alert alert-error">❌ This application was <strong>Rejected</strong>.</div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <button type="submit" name="new_status" value="Approved" class="btn btn-success">
                ✅ Reconsider & Approve
            </button>
            <button type="submit" name="new_status" value="Pending" class="btn btn-outline">
                ⏳ Move Back to Pending
            </button>
        </div>
    <?php endif; ?>
</form>

                    <div class="divider"></div>

                    <a href="/admin/dashboard.php" class="btn btn-outline btn-block" style="font-size:0.85rem;">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Confirm before rejecting
document.querySelectorAll('.btn-danger').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to REJECT this application? The applicant will be notified.')) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>

<?php
error_reporting(0);
require_once '../config.php';
requireLogin('admin');

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'bulk_update' && !empty($_POST['app_ids'])) {
        $newStatus = in_array($_POST['bulk_status'], ['Approved','Rejected','Pending']) ? $_POST['bulk_status'] : 'Pending';
        $ids = array_map('intval', $_POST['app_ids']);
        foreach ($ids as $id) {
            $conn->query("UPDATE applications SET status='$newStatus' WHERE id=$id");
        }
        flash('success', count($ids).' application(s) updated to '.$newStatus.'.');
        redirect('/admin/dashboard.php');
    }
}

$activePage = $_GET['page'] ?? 'dashboard';
$filter     = in_array($_GET['status'] ?? '', ['Pending','Approved','Rejected']) ? $_GET['status'] : '';
$search     = sanitize($_GET['q'] ?? '');

// Stats
$stats = [];
$r = $conn->query("SELECT COUNT(*) c FROM applications"); $stats['total']    = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) c FROM applications WHERE status='Pending'"); $stats['pending']  = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) c FROM applications WHERE status='Approved'"); $stats['approved'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) c FROM applications WHERE status='Rejected'"); $stats['rejected'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'"); $stats['students'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) c FROM applications WHERE DATE(submitted_at)=CURDATE()"); $stats['today'] = $r->fetch_assoc()['c'];

// Applications query
$where = []; $params = []; $types = '';
if ($filter) { $where[] = 'a.status=?'; $params[] = $filter; $types .= 's'; }
if ($search) {
    $like = "%$search%";
    $where[] = '(a.first_name LIKE ? OR a.last_name LIKE ? OR a.program LIKE ? OR u.email LIKE ? OR a.school_name LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sssss';
}
$whereSQL = $where ? 'WHERE '.implode(' AND ', $where) : '';
$sql = "SELECT a.id, a.first_name, a.last_name, a.program, a.kcse_grade, a.intake, a.status, a.submitted_at, u.email, u.phone FROM applications a JOIN users u ON a.user_id=u.id $whereSQL ORDER BY a.submitted_at DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent apps for dashboard
$recent = $conn->query("SELECT a.id, a.first_name, a.last_name, a.program, a.status, a.submitted_at FROM applications a ORDER BY a.submitted_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Program stats
$progStats = $conn->query("SELECT program, COUNT(*) cnt FROM applications GROUP BY program ORDER BY cnt DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard — OSAS | Kabarak University</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
input[type=hidden]{display:none!important}
body{font-family:'Poppins',sans-serif;background:#f0f4f8;display:flex;min-height:100vh}

.sidebar{width:230px;background:linear-gradient(180deg,#0d1b3e 0%,#122b5c 100%);min-height:100vh;position:fixed;left:0;top:0;bottom:0;display:flex;flex-direction:column;z-index:200;box-shadow:4px 0 24px rgba(0,0,0,.25);transition:transform .3s}
.sb-brand{padding:22px 20px 18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.07)}
.sb-logo{width:40px;height:40px;background:linear-gradient(135deg,#c9a227,#f0d97a);border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.sb-name{font-size:13px;font-weight:700;color:white} .sb-sub{font-size:9px;color:rgba(255,255,255,.4);margin-top:1px}
.sb-section{padding:14px 20px 4px;font-size:9px;font-weight:700;color:rgba(255,255,255,.28);text-transform:uppercase;letter-spacing:.12em}
.sb-link{display:flex;align-items:center;gap:11px;padding:10px 20px;color:rgba(255,255,255,.52);font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:all .18s;border-left:3px solid transparent;margin:1px 0}
.sb-link:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.9)}
.sb-link.active{background:rgba(201,162,39,.13);color:#f0d97a;border-left-color:#c9a227}
.sb-link .si{width:17px;text-align:center;font-size:15px;flex-shrink:0}
.sb-badge{margin-left:auto;background:rgba(255,255,255,.12);color:rgba(255,255,255,.7);padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700}
.sb-badge.red{background:rgba(239,68,68,.3);color:#fca5a5}
.sb-footer{padding:14px 16px;border-top:1px solid rgba(255,255,255,.07);margin-top:auto}
.sb-user{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.sb-ava{width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#c9a227,#f0d97a);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#0d1b3e;flex-shrink:0}
.sb-uname{font-size:12px;font-weight:600;color:white} .sb-urole{font-size:10px;color:rgba(255,255,255,.35)}
.sb-out{display:block;text-align:center;padding:8px;background:rgba(255,255,255,.05);border-radius:8px;color:rgba(255,255,255,.45);font-size:11px;text-decoration:none;transition:all .2s;border:1px solid rgba(255,255,255,.07)}
.sb-out:hover{background:rgba(255,255,255,.12);color:white}

.mob-toggle{display:none;position:fixed;top:14px;left:14px;z-index:300;background:#0d1b3e;border:none;color:white;width:38px;height:38px;border-radius:9px;font-size:18px;cursor:pointer;align-items:center;justify-content:center}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150}

.main{margin-left:230px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:white;height:60px;padding:0 24px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e5eaf2;position:sticky;top:0;z-index:100;box-shadow:0 1px 8px rgba(0,0,0,.04)}
.tb-title{font-size:15px;font-weight:600;color:#0d1b3e} .tb-sub{font-size:11px;color:#94a3b8;margin-top:1px}
.tb-right{display:flex;align-items:center;gap:12px}
.tb-ava{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,#c9a227,#f0d97a);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#0d1b3e}

.page{padding:24px;flex:1}

.flash-ok{background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:10px 16px;border-radius:9px;font-size:13px;margin-bottom:18px}
.flash-er{background:#fff1f2;border:1px solid #fda4af;color:#9f1239;padding:10px 16px;border-radius:9px;font-size:13px;margin-bottom:18px}

.stats-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:white;border-radius:13px;padding:16px 18px;border:1px solid #e5eaf2;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:transform .2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.08)}
.sc-ico{font-size:22px;margin-bottom:8px}
.sc-val{font-size:22px;font-weight:700;color:#0d1b3e;line-height:1}
.sc-lbl{font-size:10px;color:#94a3b8;margin-top:3px;font-weight:500}
.sc-total  {border-top:3px solid #3b82f6}
.sc-pending{border-top:3px solid #f59e0b}
.sc-approved{border-top:3px solid #10b981}
.sc-rejected{border-top:3px solid #ef4444}
.sc-students{border-top:3px solid #8b5cf6}
.sc-today  {border-top:3px solid #c9a227}

.two-col{display:grid;grid-template-columns:1fr 320px;gap:18px;align-items:start}
.card{background:white;border-radius:13px;border:1px solid #e5eaf2;box-shadow:0 1px 4px rgba(0,0,0,.04);overflow:hidden;margin-bottom:16px}
.card:last-child{margin-bottom:0}
.card-head{padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9}
.card-title{font-size:13px;font-weight:600;color:#0d1b3e;display:flex;align-items:center;gap:7px}
.card-body{padding:20px}

/* FILTER BAR */
.filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.filter-chip{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s;border:1.5px solid #e5eaf2;color:#64748b;background:white}
.filter-chip:hover{border-color:#0d1b3e;color:#0d1b3e}
.filter-chip.active{background:#0d1b3e;color:white;border-color:#0d1b3e}
.search-box{display:flex;gap:8px;margin-left:auto}
.search-box input{padding:7px 13px;border:1.5px solid #e5eaf2;border-radius:8px;font-family:'Poppins',sans-serif;font-size:12px;width:220px;outline:none;transition:all .2s}
.search-box input:focus{border-color:#0d1b3e}
.btn-search{padding:7px 16px;background:#0d1b3e;color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:'Poppins',sans-serif}

/* TABLE */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:2px solid #e5eaf2;white-space:nowrap}
tbody td{padding:13px 14px;border-bottom:1px solid #f1f5f9;font-size:12px;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:#f8fafc}
.badge-pending {background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;white-space:nowrap}
.badge-approved{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;white-space:nowrap}
.badge-rejected{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;white-space:nowrap}
.btn-view{padding:5px 14px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;font-family:'Poppins',sans-serif;white-space:nowrap}
.btn-view:hover{background:#dbeafe}
.chk{width:15px;height:15px;accent-color:#0d1b3e;cursor:pointer}

/* BULK BAR */
.bulk-bar{display:none;background:#0d1b3e;border-radius:10px;padding:12px 18px;margin-bottom:14px;align-items:center;gap:12px;color:white;font-size:13px}
.bulk-bar.show{display:flex}
.bulk-select{padding:6px 12px;border-radius:7px;border:none;font-size:12px;font-weight:600;font-family:'Poppins',sans-serif;background:rgba(255,255,255,.15);color:white;cursor:pointer}
.bulk-select option{color:#0d1b3e;background:white}
.bulk-apply{padding:6px 16px;background:#c9a227;color:#0d1b3e;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Poppins',sans-serif}

/* RECENT ROW */
.recent-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9}
.recent-row:last-child{border-bottom:none}
.rr-ava{width:34px;height:34px;border-radius:9px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#0d1b3e;flex-shrink:0}
.rr-name{font-size:12px;font-weight:600;color:#0d1b3e}
.rr-prog{font-size:11px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px}
.rr-badge{margin-left:auto;flex-shrink:0}

/* PROG BAR */
.prog-item{margin-bottom:12px}
.prog-item:last-child{margin-bottom:0}
.prog-label{display:flex;justify-content:space-between;font-size:11px;color:#475569;margin-bottom:4px}
.prog-label span:last-child{font-weight:700;color:#0d1b3e}
.prog-bar{height:7px;background:#f1f5f9;border-radius:10px;overflow:hidden}
.prog-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,#0d1b3e,#1e40af);transition:width .6s ease}

/* SETTINGS */
.setting-row{display:flex;align-items:flex-start;justify-content:space-between;padding:16px 0;border-bottom:1px solid #f1f5f9;gap:20px}
.setting-row:last-child{border-bottom:none}
.setting-label{font-size:13px;font-weight:600;color:#0d1b3e;margin-bottom:3px}
.setting-desc{font-size:11px;color:#94a3b8;line-height:1.5}
.setting-input{min-width:220px}
.setting-input input,.setting-input select{width:100%;padding:8px 12px;border:1.5px solid #e5eaf2;border-radius:8px;font-size:12px;font-family:'Poppins',sans-serif;outline:none;transition:border .2s}
.setting-input input:focus,.setting-input select:focus{border-color:#0d1b3e}
.btn-save{padding:8px 20px;background:#0d1b3e;color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:'Poppins',sans-serif;margin-top:8px}

/* EMPTY */
.empty{text-align:center;padding:40px 20px;color:#94a3b8}
.empty-ico{font-size:36px;margin-bottom:10px}

@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.two-col{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.main{margin-left:0}.mob-toggle{display:flex}.overlay.show{display:block}.topbar{padding:0 16px 0 58px}.page{padding:16px}}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr 1fr}.filter-bar{gap:6px}.search-box{margin-left:0;width:100%}.search-box input{width:100%}}
</style>
</head>
<body>

<button class="mob-toggle" id="mobToggle">☰</button>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="sb-logo">🎓</div>
        <div><div class="sb-name">Kabarak University</div><div class="sb-sub">Admin Control Panel</div></div>
    </div>
    <div style="flex:1;overflow-y:auto;padding:8px 0;">
        <div class="sb-section">Overview</div>
        <a href="/admin/dashboard.php" class="sb-link <?php echo $activePage==='dashboard'?'active':''; ?>" onclick="closeSidebar()">
            <span class="si">⊞</span> Dashboard
        </a>
        <div class="sb-section">Applications</div>
        <a href="/admin/dashboard.php?page=applications" class="sb-link <?php echo $activePage==='applications'?'active':''; ?>" onclick="closeSidebar()">
            <span class="si">📋</span> All Applications
            <?php if ($stats['pending'] > 0): ?>
            <span class="sb-badge red"><?php echo $stats['pending']; ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/dashboard.php?page=applications&status=Pending" class="sb-link" onclick="closeSidebar()">
            <span class="si">⏳</span> Pending Review
            <?php if ($stats['pending'] > 0): ?>
            <span class="sb-badge red"><?php echo $stats['pending']; ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/dashboard.php?page=applications&status=Approved" class="sb-link" onclick="closeSidebar()">
            <span class="si">✅</span> Approved
            <span class="sb-badge"><?php echo $stats['approved']; ?></span>
        </a>
        <a href="/admin/dashboard.php?page=applications&status=Rejected" class="sb-link" onclick="closeSidebar()">
            <span class="si">❌</span> Rejected
            <span class="sb-badge"><?php echo $stats['rejected']; ?></span>
        </a>
        <div class="sb-section">Management</div>
        <a href="/admin/dashboard.php?page=students" class="sb-link <?php echo $activePage==='students'?'active':''; ?>" onclick="closeSidebar()">
            <span class="si">👥</span> Students
            <span class="sb-badge"><?php echo $stats['students']; ?></span>
        </a>
        <a href="/admin/dashboard.php?page=reports" class="sb-link <?php echo $activePage==='reports'?'active':''; ?>" onclick="closeSidebar()">
            <span class="si">📊</span> Reports
        </a>
        <div class="sb-section">System</div>
        <a href="/admin/dashboard.php?page=settings" class="sb-link <?php echo $activePage==='settings'?'active':''; ?>" onclick="closeSidebar()">
            <span class="si">⚙️</span> Settings
        </a>
    </div>
    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-ava"><?php echo initials($_SESSION['full_name']); ?></div>
            <div><div class="sb-uname"><?php echo h($_SESSION['full_name']); ?></div><div class="sb-urole">Administrator</div></div>
        </div>
        <a href="/logout.php" class="sb-out">⎋ &nbsp;Sign Out</a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <header class="topbar">
        <div>
            <div class="tb-title"><?php
                $titles = array('dashboard'=>'Dashboard','applications'=>'Applications','students'=>'Students','reports'=>'Reports','settings'=>'Settings');
                echo isset($titles[$activePage]) ? $titles[$activePage] : 'Dashboard';
            ?></div>
            <div class="tb-sub">Kabarak University &mdash; Administration Panel</div>
        </div>
        <div class="tb-right">
            <div style="font-size:11px;color:#94a3b8;"><?php echo date('d M Y'); ?></div>
            <div class="tb-ava"><?php echo initials($_SESSION['full_name']); ?></div>
        </div>
    </header>

    <div class="page">
    <?php
    $f = getFlash();
    if ($f) {
        $cls = ($f['type']==='success') ? 'flash-ok' : 'flash-er';
        echo '<div class="'.$cls.'">'.($f['type']==='success'?'✅ ':'❌ ').h($f['message']).'</div>';
    }
    ?>

    <?php if ($activePage==='dashboard'): ?>
    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card sc-total"><div class="sc-ico">📋</div><div class="sc-val"><?php echo $stats['total']; ?></div><div class="sc-lbl">Total Applications</div></div>
        <div class="stat-card sc-pending"><div class="sc-ico">⏳</div><div class="sc-val"><?php echo $stats['pending']; ?></div><div class="sc-lbl">Pending Review</div></div>
        <div class="stat-card sc-approved"><div class="sc-ico">✅</div><div class="sc-val"><?php echo $stats['approved']; ?></div><div class="sc-lbl">Approved</div></div>
        <div class="stat-card sc-rejected"><div class="sc-ico">❌</div><div class="sc-val"><?php echo $stats['rejected']; ?></div><div class="sc-lbl">Rejected</div></div>
        <div class="stat-card sc-students"><div class="sc-ico">👥</div><div class="sc-val"><?php echo $stats['students']; ?></div><div class="sc-lbl">Registered Students</div></div>
        <div class="stat-card sc-today"><div class="sc-ico">📅</div><div class="sc-val"><?php echo $stats['today']; ?></div><div class="sc-lbl">Today's Applications</div></div>
    </div>

    <div class="two-col">
        <div>
            <div class="card">
                <div class="card-head">
                    <div class="card-title">🕐 Recent Applications</div>
                    <a href="/admin/dashboard.php?page=applications" style="font-size:12px;color:#1d4ed8;text-decoration:none;font-weight:500;">View all &rarr;</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent)): ?>
                    <div class="empty"><div class="empty-ico">📭</div>No applications yet</div>
                    <?php else: ?>
                    <?php foreach ($recent as $a): ?>
                    <div class="recent-row">
                        <div class="rr-ava"><?php echo strtoupper(substr($a['first_name'],0,1).substr($a['last_name'],0,1)); ?></div>
                        <div style="flex:1;min-width:0;">
                            <div class="rr-name"><?php echo h($a['first_name'].' '.$a['last_name']); ?></div>
                            <div class="rr-prog"><?php echo h($a['program']); ?></div>
                        </div>
                        <div class="rr-badge">
                            <span class="badge-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span>
                        </div>
                        <a href="/admin/view.php?id=<?php echo $a['id']; ?>" class="btn-view" style="margin-left:8px;">View</a>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div>
            <div class="card">
                <div class="card-head"><div class="card-title">📊 Applications by Programme</div></div>
                <div class="card-body">
                    <?php if ($stats['total'] > 0): ?>
                    <?php foreach ($progStats as $p): ?>
                    <div class="prog-item">
                        <div class="prog-label">
                            <span style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo h($p['program']); ?></span>
                            <span><?php echo $p['cnt']; ?></span>
                        </div>
                        <div class="prog-bar">
                            <div class="prog-fill" style="width:<?php echo min(100, round($p['cnt']/$stats['total']*100)); ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty"><div class="empty-ico">📊</div>No data yet</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><div class="card-title">⚡ Quick Actions</div></div>
                <div class="card-body" style="display:grid;gap:8px;">
                    <a href="/admin/dashboard.php?page=applications&status=Pending" style="display:flex;align-items:center;gap:10px;padding:12px;background:#fff7ed;border-radius:9px;text-decoration:none;border:1px solid #fed7aa;transition:all .2s;" onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fff7ed'">
                        <span style="font-size:18px;">⏳</span>
                        <div><div style="font-size:12px;font-weight:600;color:#92400e;">Review Pending</div><div style="font-size:10px;color:#c2410c;"><?php echo $stats['pending']; ?> application(s) waiting</div></div>
                    </a>
                    <a href="/admin/dashboard.php?page=students" style="display:flex;align-items:center;gap:10px;padding:12px;background:#f5f3ff;border-radius:9px;text-decoration:none;border:1px solid #ddd6fe;transition:all .2s;" onmouseover="this.style.background='#ede9fe'" onmouseout="this.style.background='#f5f3ff'">
                        <span style="font-size:18px;">👥</span>
                        <div><div style="font-size:12px;font-weight:600;color:#5b21b6;">Manage Students</div><div style="font-size:10px;color:#7c3aed;"><?php echo $stats['students']; ?> registered students</div></div>
                    </a>
                    <a href="/admin/dashboard.php?page=reports" style="display:flex;align-items:center;gap:10px;padding:12px;background:#f0fdf4;border-radius:9px;text-decoration:none;border:1px solid #bbf7d0;transition:all .2s;" onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
                        <span style="font-size:18px;">📊</span>
                        <div><div style="font-size:12px;font-weight:600;color:#166534;">View Reports</div><div style="font-size:10px;color:#16a34a;">Analytics &amp; summaries</div></div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($activePage==='applications'): ?>
    <!-- ALL APPS PAGE -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">📋 All Applications</div>
            <div style="font-size:12px;color:#94a3b8;"><?php echo count($apps); ?> result(s)</div>
        </div>
        <div class="card-body">
            <form method="GET" action="/admin/dashboard.php">
                <input type="hidden" name="page" value="applications">
                <div class="filter-bar">
                    <a href="/admin/dashboard.php?page=applications" class="filter-chip <?php echo !$filter?'active':''; ?>">All (<?php echo $stats['total']; ?>)</a>
                    <a href="/admin/dashboard.php?page=applications&status=Pending" class="filter-chip <?php echo $filter==='Pending'?'active':''; ?>">⏳ Pending (<?php echo $stats['pending']; ?>)</a>
                    <a href="/admin/dashboard.php?page=applications&status=Approved" class="filter-chip <?php echo $filter==='Approved'?'active':''; ?>">✅ Approved (<?php echo $stats['approved']; ?>)</a>
                    <a href="/admin/dashboard.php?page=applications&status=Rejected" class="filter-chip <?php echo $filter==='Rejected'?'active':''; ?>">❌ Rejected (<?php echo $stats['rejected']; ?>)</a>
                    <div class="search-box">
                        <?php if ($filter): ?><input type="hidden" name="status" value="<?php echo h($filter); ?>"><?php endif; ?>
                        <input type="text" name="q" value="<?php echo h($search); ?>" placeholder="Search name, programme, email…">
                        <button type="submit" class="btn-search">🔍 Search</button>
                        <?php if ($search): ?><a href="/admin/dashboard.php?page=applications<?php echo $filter?'&status='.$filter:''; ?>" style="padding:7px 14px;background:#f1f5f9;color:#64748b;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;">✕ Clear</a><?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- BULK -->
            <form method="POST" id="bulkForm">
                <input type="hidden" name="action" value="bulk_update">
                <div class="bulk-bar" id="bulkBar">
                    <span id="bulkCount">0</span> selected &nbsp;
                    <select name="bulk_status" class="bulk-select">
                        <option value="Approved">✅ Approve All</option>
                        <option value="Rejected">❌ Reject All</option>
                        <option value="Pending">⏳ Set Pending</option>
                    </select>
                    <button type="submit" class="bulk-apply" onclick="return confirm('Apply this action to all selected applications?')">Apply</button>
                    <button type="button" style="background:rgba(255,255,255,.1);color:white;border:none;border-radius:7px;padding:6px 12px;font-size:12px;cursor:pointer;font-family:'Poppins',sans-serif;" onclick="clearSelection()">Cancel</button>
                </div>

                <?php if (empty($apps)): ?>
                <div class="empty"><div class="empty-ico">🗃️</div><div style="font-size:14px;font-weight:600;margin-bottom:6px;">No applications found</div><div style="font-size:12px;">Try changing your filters or search query.</div></div>
                <?php else: ?>
                <div class="tbl-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="chk" id="selectAll" onchange="toggleAll(this)"></th>
                                <th>#</th><th>Applicant</th><th>Programme</th><th>Grade</th><th>Intake</th><th>Submitted</th><th>Status</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apps as $a): ?>
                            <tr>
                                <td><input type="checkbox" name="app_ids[]" value="<?php echo $a['id']; ?>" class="chk row-chk" onchange="updateBulkBar()"></td>
                                <td style="color:#94a3b8;font-weight:600;">#<?php echo $a['id']; ?></td>
                                <td>
                                    <div style="font-weight:600;font-size:13px;color:#0d1b3e;"><?php echo h($a['first_name'].' '.$a['last_name']); ?></div>
                                    <div style="font-size:11px;color:#94a3b8;"><?php echo h($a['email']); ?></div>
                                </td>
                                <td style="font-size:11px;max-width:160px;"><?php echo h($a['program']); ?></td>
                                <td style="font-weight:700;font-size:14px;"><?php echo h($a['kcse_grade']); ?></td>
                                <td style="font-size:11px;"><?php echo h($a['intake']); ?></td>
                                <td style="font-size:11px;color:#94a3b8;"><?php echo date('d M Y', strtotime($a['submitted_at'])); ?></td>
                                <td><span class="badge-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                                <td><a href="/admin/view.php?id=<?php echo $a['id']; ?>" class="btn-view">👁 Review</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php elseif ($activePage==='students'): ?>
    <!-- STUDENTS PAGE -->
    <?php
    $students = $conn->query("SELECT u.id, u.full_name, u.email, u.phone, u.created_at, a.status app_status, a.program FROM users u LEFT JOIN applications a ON a.user_id=u.id WHERE u.role='student' ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);
    ?>
    <div class="card">
        <div class="card-head">
            <div class="card-title">👥 Registered Students</div>
            <div style="font-size:12px;color:#94a3b8;"><?php echo count($students); ?> students</div>
        </div>
        <div class="card-body">
            <?php if (empty($students)): ?>
            <div class="empty"><div class="empty-ico">👥</div>No students registered yet.</div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>#</th><th>Student</th><th>Phone</th><th>Programme</th><th>App Status</th><th>Registered</th></tr></thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td style="color:#94a3b8;font-weight:600;"><?php echo $s['id']; ?></td>
                            <td>
                                <div style="font-weight:600;font-size:13px;color:#0d1b3e;"><?php echo h($s['full_name']); ?></div>
                                <div style="font-size:11px;color:#94a3b8;"><?php echo h($s['email']); ?></div>
                            </td>
                            <td style="font-size:12px;"><?php echo h($s['phone']); ?></td>
                            <td style="font-size:11px;max-width:160px;"><?php echo $s['program'] ? h($s['program']) : '<span style="color:#94a3b8;">No application</span>'; ?></td>
                            <td>
                                <?php if ($s['app_status']): ?>
                                <span class="badge-<?php echo strtolower($s['app_status']); ?>"><?php echo $s['app_status']; ?></span>
                                <?php else: ?>
                                <span style="font-size:11px;color:#94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:11px;color:#94a3b8;"><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($activePage==='reports'): ?>
    <!-- REPORTS PAGE -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
        <div class="card">
            <div class="card-head"><div class="card-title">📊 Application Summary</div></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div style="background:#f0fdf4;border-radius:10px;padding:14px;text-align:center;border:1px solid #bbf7d0;">
                        <div style="font-size:28px;font-weight:800;color:#166534;"><?php echo $stats['approved']; ?></div>
                        <div style="font-size:11px;color:#16a34a;font-weight:500;">Approved</div>
                    </div>
                    <div style="background:#fff7ed;border-radius:10px;padding:14px;text-align:center;border:1px solid #fed7aa;">
                        <div style="font-size:28px;font-weight:800;color:#c2410c;"><?php echo $stats['pending']; ?></div>
                        <div style="font-size:11px;color:#ea580c;font-weight:500;">Pending</div>
                    </div>
                    <div style="background:#fff1f2;border-radius:10px;padding:14px;text-align:center;border:1px solid #fecdd3;">
                        <div style="font-size:28px;font-weight:800;color:#9f1239;"><?php echo $stats['rejected']; ?></div>
                        <div style="font-size:11px;color:#e11d48;font-weight:500;">Rejected</div>
                    </div>
                    <div style="background:#f5f3ff;border-radius:10px;padding:14px;text-align:center;border:1px solid #ddd6fe;">
                        <div style="font-size:28px;font-weight:800;color:#5b21b6;"><?php echo $stats['total']; ?></div>
                        <div style="font-size:11px;color:#7c3aed;font-weight:500;">Total</div>
                    </div>
                </div>
                <?php if ($stats['total'] > 0): ?>
                <div style="font-size:11px;color:#94a3b8;margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Approval Rate</div>
                <div style="height:10px;background:#f1f5f9;border-radius:10px;overflow:hidden;">
                    <div style="height:100%;width:<?php echo round($stats['approved']/$stats['total']*100); ?>%;background:linear-gradient(90deg,#10b981,#059669);border-radius:10px;"></div>
                </div>
                <div style="font-size:12px;color:#166534;font-weight:600;margin-top:5px;"><?php echo round($stats['approved']/$stats['total']*100); ?>% approval rate</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><div class="card-title">🎓 Top Programmes</div></div>
            <div class="card-body">
                <?php foreach ($progStats as $p): ?>
                <div class="prog-item">
                    <div class="prog-label">
                        <span style="font-size:11px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo h($p['program']); ?></span>
                        <span><?php echo $p['cnt']; ?> apps</span>
                    </div>
                    <div class="prog-bar">
                        <div class="prog-fill" style="width:<?php echo $stats['total'] > 0 ? min(100, round($p['cnt']/$stats['total']*100)) : 0; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    $gradeStats = $conn->query("SELECT kcse_grade, COUNT(*) cnt FROM applications GROUP BY kcse_grade ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
    ?>
    <div class="card" style="margin-top:18px;">
        <div class="card-head"><div class="card-title">📈 Applications by KCSE Grade</div></div>
        <div class="card-body">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach ($gradeStats as $g): ?>
                <div style="background:#f8fafc;border:1px solid #e5eaf2;border-radius:10px;padding:12px 18px;text-align:center;min-width:70px;">
                    <div style="font-size:20px;font-weight:800;color:#0d1b3e;"><?php echo h($g['kcse_grade']); ?></div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;"><?php echo $g['cnt']; ?> student<?php echo $g['cnt']!=1?'s':''; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php elseif ($activePage==='settings'): ?>
    <!-- SETTINGS PAGE -->
    <div class="card">
        <div class="card-head"><div class="card-title">⚙️ System Settings</div></div>
        <div class="card-body">
            <form method="POST" action="/admin/settings_save.php">
                <div class="setting-row">
                    <div><div class="setting-label">Administrator Password</div><div class="setting-desc">Change the password for this admin account. Use a strong password with letters and numbers.</div></div>
                    <div class="setting-input">
                        <input type="password" name="new_password" placeholder="New password">
                        <input type="password" name="confirm_password" placeholder="Confirm new password" style="margin-top:6px;">
                        <button type="submit" name="action" value="change_password" class="btn-save">Update Password</button>
                    </div>
                </div>
                <div class="setting-row">
                    <div><div class="setting-label">Default Intake Period</div><div class="setting-desc">Set the default intake period shown on the application form.</div></div>
                    <div class="setting-input">
                        <select name="default_intake">
                            <option>September 2026</option>
                            <option>January 2027</option>
                            <option>May 2027</option>
                        </select>
                        <button type="submit" name="action" value="save_intake" class="btn-save">Save</button>
                    </div>
                </div>
                <div class="setting-row">
                    <div><div class="setting-label">Admin Email</div><div class="setting-desc">Update the email address used for this admin account.</div></div>
                    <div class="setting-input">
                        <input type="email" name="admin_email" value="<?php echo h($_SESSION['email'] ?? 'admin@osas.ac.ke'); ?>">
                        <button type="submit" name="action" value="update_email" class="btn-save">Update Email</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="card" style="margin-top:18px;">
        <div class="card-head"><div class="card-title">🗄️ Database Info</div></div>
        <div class="card-body">
            <?php
            $tbl = $conn->query("SHOW TABLE STATUS FROM osas_db")->fetch_all(MYSQLI_ASSOC);
            ?>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Table</th><th>Rows</th><th>Engine</th></tr></thead>
                    <tbody>
                        <?php foreach ($tbl as $t): ?>
                        <tr>
                            <td style="font-weight:600;font-size:12px;"><?php echo h($t['Name']); ?></td>
                            <td style="font-size:12px;"><?php echo number_format($t['Rows']); ?></td>
                            <td style="font-size:11px;color:#94a3b8;"><?php echo h($t['Engine']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
document.getElementById('mobToggle').addEventListener('click',openSidebar);

function toggleAll(cb){
    document.querySelectorAll('.row-chk').forEach(function(c){c.checked=cb.checked;});
    updateBulkBar();
}
function updateBulkBar(){
    var checked=document.querySelectorAll('.row-chk:checked').length;
    var bar=document.getElementById('bulkBar');
    if(bar){
        if(checked>0){bar.classList.add('show');document.getElementById('bulkCount').textContent=checked;}
        else{bar.classList.remove('show');}
    }
}
function clearSelection(){
    document.querySelectorAll('.row-chk').forEach(function(c){c.checked=false;});
    var sa=document.getElementById('selectAll');
    if(sa)sa.checked=false;
    var bar=document.getElementById('bulkBar');
    if(bar)bar.classList.remove('show');
}
</script>
</body>
</html>

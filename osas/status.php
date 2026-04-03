<?php
error_reporting(0);
require_once 'config.php';
requireLogin('student');

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT a.*, u.full_name, u.email, u.phone FROM applications a JOIN users u ON a.user_id = u.id WHERE a.user_id = ? ORDER BY a.submitted_at DESC LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

$docs = [];
if ($app) {
    $ds = $conn->prepare("SELECT * FROM documents WHERE application_id = ?");
    $ds->bind_param('i', $app['id']);
    $ds->execute();
    $docs = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
    $ds->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_doc') {
        $appId   = (int)($app['id'] ?? 0);
        $docType = sanitize($_POST['doc_type'] ?? '');
        $docId   = (int)($_POST['doc_id'] ?? 0);
        if ($appId && isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $origName = $_FILES['doc_file']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, array('pdf','jpg','jpeg','png'))) {
                flash('error', 'Only PDF, JPG, or PNG files are allowed.');
            } elseif ($_FILES['doc_file']['size'] > 2097152) {
                flash('error', 'File size must not exceed 2MB.');
            } else {
                $newName = uniqid('doc_') . '.' . $ext;
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $uploadDir . $newName)) {
                    if ($docId > 0) {
                        $old = $conn->prepare("SELECT file_path FROM documents WHERE id=?");
                        $old->bind_param('i', $docId); $old->execute();
                        $r = $old->get_result()->fetch_assoc(); $old->close();
                        if ($r && file_exists($uploadDir . $r['file_path'])) @unlink($uploadDir . $r['file_path']);
                        $u = $conn->prepare("UPDATE documents SET file_name=?, file_path=?, uploaded_at=NOW() WHERE id=? AND application_id=?");
                        $u->bind_param('ssii', $origName, $newName, $docId, $appId);
                        $u->execute(); $u->close();
                    } else {
                        $ins = $conn->prepare("INSERT INTO documents (application_id, doc_type, file_name, file_path) VALUES (?,?,?,?)");
                        $ins->bind_param('isss', $appId, $docType, $origName, $newName);
                        $ins->execute(); $ins->close();
                    }
                    flash('success', $docType . ' uploaded successfully!');
                } else { flash('error', 'Upload failed.'); }
            }
        } else { flash('error', 'Please select a file.'); }
        header('Location: status.php'); exit;
    }
    if ($action === 'delete_doc') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $appId = (int)($app['id'] ?? 0);
        if ($docId && $appId) {
            $old = $conn->prepare("SELECT file_path FROM documents WHERE id=? AND application_id=?");
            $old->bind_param('ii', $docId, $appId); $old->execute();
            $r = $old->get_result()->fetch_assoc(); $old->close();
            if ($r) {
                $uploadDir = __DIR__ . '/uploads/';
                if (file_exists($uploadDir . $r['file_path'])) @unlink($uploadDir . $r['file_path']);
                $d = $conn->prepare("DELETE FROM documents WHERE id=? AND application_id=?");
                $d->bind_param('ii', $docId, $appId); $d->execute(); $d->close();
                flash('success', 'Document removed.');
            }
        }
        header('Location: status.php'); exit;
    }
}

$docSlots = array('cert' => 'KCSE Certificate', 'id_doc' => 'National ID / Birth Cert', 'photo' => 'Passport Photo');
$docsByType = array();
foreach ($docs as $d) {
    foreach ($docSlots as $f => $l) {
        if ($d['doc_type'] === $l) $docsByType[$f] = $d;
    }
}

$status       = $app['status'] ?? '';
$fullName     = $_SESSION['full_name'];
$firstName    = explode(' ', trim($fullName))[0];
$adminComment = $app['admin_comment'] ?? '';
$docCount     = count($docsByType);
$totalDocs    = count($docSlots);

if ($status === 'Approved')     { $chipClass = 'chip-approved'; $chipLabel = 'Approved'; }
elseif ($status === 'Rejected') { $chipClass = 'chip-rejected'; $chipLabel = 'Not Successful'; }
else                            { $chipClass = 'chip-pending';  $chipLabel = 'Under Review'; }

$f = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Student Dashboard - OSAS | Kabarak University</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
input[type=hidden] { display: none !important; }
body { font-family: 'Poppins', sans-serif; background: #f0f4f8; display: flex; min-height: 100vh; }

/* SIDEBAR */
.sidebar {
    width: 220px; background: linear-gradient(180deg, #0d1b3e 0%, #122b5c 100%);
    min-height: 100vh; position: fixed; left: 0; top: 0; bottom: 0;
    display: flex; flex-direction: column; z-index: 200;
    box-shadow: 4px 0 24px rgba(0,0,0,.25);
}
.sb-brand {
    padding: 22px 20px 18px; display: flex; align-items: center; gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.sb-logo {
    width: 40px; height: 40px; background: linear-gradient(135deg, #c9a227, #f0d97a);
    border-radius: 11px; display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.sb-name { font-size: 13px; font-weight: 700; color: white; line-height: 1.3; }
.sb-sub  { font-size: 9px; color: rgba(255,255,255,.4); margin-top: 1px; }
.sb-section {
    padding: 14px 20px 4px; font-size: 9px; font-weight: 700;
    color: rgba(255,255,255,.28); text-transform: uppercase; letter-spacing: .12em;
}
.sb-item {
    display: flex; align-items: center; gap: 11px; padding: 11px 20px;
    color: rgba(255,255,255,.55); font-size: 13px; font-weight: 500;
    cursor: pointer; border: none; background: none; width: 100%; text-align: left;
    font-family: 'Poppins', sans-serif; transition: all .18s;
    border-left: 3px solid transparent;
}
.sb-item:hover { background: rgba(255,255,255,.08); color: white; }
.sb-item.active { background: rgba(201,162,39,.15); color: #f0d97a; border-left-color: #c9a227; }
.sb-item .si { width: 18px; text-align: center; font-size: 15px; flex-shrink: 0; }
.sb-badge {
    margin-left: auto; padding: 1px 8px; border-radius: 10px;
    font-size: 10px; font-weight: 700;
}
.sb-footer {
    padding: 14px 16px; border-top: 1px solid rgba(255,255,255,.07); margin-top: auto;
}
.sb-user { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.sb-ava {
    width: 36px; height: 36px; border-radius: 9px;
    background: linear-gradient(135deg, #c9a227, #f0d97a);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 12px; color: #0d1b3e; flex-shrink: 0;
}
.sb-uname { font-size: 12px; font-weight: 600; color: white; }
.sb-urole { font-size: 10px; color: rgba(255,255,255,.35); }
.sb-out {
    display: block; text-align: center; padding: 8px;
    background: rgba(255,255,255,.05); border-radius: 8px;
    color: rgba(255,255,255,.5); font-size: 11px; text-decoration: none;
    transition: all .2s; border: 1px solid rgba(255,255,255,.07);
}
.sb-out:hover { background: rgba(255,255,255,.12); color: white; }

/* MAIN */
.main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; }
.topbar {
    background: white; height: 60px; padding: 0 24px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid #e5eaf2; position: sticky; top: 0; z-index: 100;
    box-shadow: 0 1px 8px rgba(0,0,0,.04);
}
.tb-title { font-size: 15px; font-weight: 600; color: #0d1b3e; }
.tb-sub   { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.chip { display: inline-flex; align-items: center; padding: 4px 13px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.chip-pending  { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
.chip-approved { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.chip-rejected { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }

/* PAGE CONTENT */
.page { padding: 24px; flex: 1; }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* FLASH */
.flash-ok { background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 10px 16px; border-radius: 9px; font-size: 13px; margin-bottom: 18px; }
.flash-er { background: #fff1f2; border: 1px solid #fda4af; color: #9f1239; padding: 10px 16px; border-radius: 9px; font-size: 13px; margin-bottom: 18px; }

/* HERO BANNER */
.hero { border-radius: 14px; padding: 26px 30px; margin-bottom: 22px; position: relative; overflow: hidden; }
.hero-pending  { background: linear-gradient(120deg, #0d1b3e, #1e40af); }
.hero-approved { background: linear-gradient(120deg, #064e3b, #059669); }
.hero-rejected { background: linear-gradient(120deg, #450a0a, #991b1b); }
.hero h2 { font-size: 20px; font-weight: 700; color: white; margin-bottom: 8px; }
.hero p  { font-size: 13px; color: rgba(255,255,255,.75); line-height: 1.6; max-width: 560px; }
.hero-deco { position: absolute; right: 28px; top: 50%; transform: translateY(-50%); font-size: 80px; opacity: .1; }
.hero-tag { display: inline-block; margin-top: 12px; background: rgba(255,255,255,.15); color: white; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; }

/* STATS */
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 22px; }
.stat { background: white; border-radius: 13px; padding: 18px 20px; display: flex; align-items: center; gap: 14px; border: 1px solid #e5eaf2; box-shadow: 0 1px 4px rgba(0,0,0,.04); transition: transform .2s; }
.stat:hover { transform: translateY(-2px); }
.stat-ico { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.ic-b { background: #eff6ff; } .ic-g { background: #fefce8; } .ic-gr { background: #f0fdf4; }
.sv { font-size: 22px; font-weight: 700; color: #0d1b3e; line-height: 1; }
.sl { font-size: 11px; color: #94a3b8; margin-top: 2px; }

/* QUICK LINKS */
.qgrid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 22px; }
.qlink { background: white; border: 1px solid #e5eaf2; border-radius: 13px; padding: 20px 16px; text-align: center; cursor: pointer; transition: all .2s; border-top: 3px solid transparent; }
.qlink:hover { border-top-color: #0d1b3e; transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.qlink-ico   { font-size: 26px; margin-bottom: 8px; }
.qlink-title { font-size: 12px; font-weight: 600; color: #0d1b3e; }
.qlink-sub   { font-size: 10px; color: #94a3b8; margin-top: 3px; }

/* CARD */
.card { background: white; border-radius: 13px; border: 1px solid #e5eaf2; box-shadow: 0 1px 4px rgba(0,0,0,.04); overflow: hidden; margin-bottom: 16px; }
.card:last-child { margin-bottom: 0; }
.card-head { padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; }
.card-title { font-size: 13px; font-weight: 600; color: #0d1b3e; }
.card-badge { font-size: 10px; font-weight: 600; background: #f1f5f9; color: #64748b; padding: 2px 10px; border-radius: 10px; }
.card-body  { padding: 20px; }

/* INFO ROWS */
.ir { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f8fafc; font-size: 13px; }
.ir:last-child { border-bottom: none; padding-bottom: 0; }
.ik { color: #94a3b8; font-weight: 500; }
.iv { color: #0d1b3e; font-weight: 600; text-align: right; max-width: 58%; }

/* PROGRESS */
.two-col { display: grid; grid-template-columns: 1fr 300px; gap: 18px; align-items: start; }
.step { display: flex; gap: 13px; padding: 9px 0; position: relative; }
.step:not(:last-child)::after { content: ''; position: absolute; left: 15px; top: 38px; width: 2px; height: calc(100% - 8px); background: #e5eaf2; }
.step-dot { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; z-index: 1; }
.sd-done { background: #dcfce7; color: #166534; border: 2px solid #86efac; }
.sd-act  { background: #fef3c7; color: #92400e; border: 2px solid #fcd34d; }
.sd-wait { background: #f1f5f9; color: #94a3b8; border: 2px solid #e5eaf2; }
.sd-fail { background: #fee2e2; color: #991b1b; border: 2px solid #fca5a5; }
.step-info strong { font-size: 12px; font-weight: 600; color: #0d1b3e; display: block; margin-top: 4px; }
.step-info span   { font-size: 11px; color: #94a3b8; }

/* COMMENT */
.comment { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 13px 16px; margin-bottom: 18px; }
.c-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #1d4ed8; margin-bottom: 4px; }
.c-txt { font-size: 13px; color: #1e40af; line-height: 1.6; }

/* CONTACT */
.citem { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 12px; color: #475569; }
.citem:last-child { border-bottom: none; }
.ci-ico { width: 29px; height: 29px; background: #f1f5f9; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }

/* DOCUMENTS */
.doc-row { display: flex; align-items: center; gap: 13px; padding: 14px 16px; border-radius: 10px; border: 1px solid #e5eaf2; margin-bottom: 10px; background: #fafafa; }
.doc-row:last-child { margin-bottom: 0; }
.doc-row.has-doc { background: #f0fdf4; border-color: #bbf7d0; }
.doc-ico { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
.doc-row.has-doc .doc-ico { background: #dcfce7; }
.doc-row:not(.has-doc) .doc-ico { background: #f1f5f9; }
.doc-inf { flex: 1; min-width: 0; }
.doc-nm  { font-size: 12px; font-weight: 600; color: #0d1b3e; }
.doc-fn  { font-size: 11px; color: #94a3b8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-act { display: flex; gap: 6px; flex-shrink: 0; }
.dbtn { padding: 5px 12px; border-radius: 7px; font-size: 11px; font-weight: 600; cursor: pointer; border: none; font-family: 'Poppins', sans-serif; transition: all .2s; white-space: nowrap; }
.db-up  { background: #0d1b3e; color: white; }
.db-rep { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.db-del { background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; padding: 5px 9px; }

/* UPLOAD PANEL */
.upanel { display: none; margin-top: 10px; padding: 14px; background: white; border: 1.5px solid #c7d2fe; border-radius: 10px; }
.upanel.open { display: block; }
.dropzone { border: 2px dashed #c7d2fe; border-radius: 8px; padding: 22px 16px; text-align: center; cursor: pointer; background: #f8faff; position: relative; transition: all .2s; }
.dropzone:hover { border-color: #4f46e5; background: #eef2ff; }
.dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.dz-ico { font-size: 22px; margin-bottom: 5px; }
.dz-txt { font-size: 12px; color: #64748b; line-height: 1.5; }
.dz-txt strong { color: #4f46e5; }
.file-ok { font-size: 11px; font-weight: 500; margin-top: 8px; padding: 5px 10px; border-radius: 6px; display: none; }
.up-btns { display: flex; gap: 8px; margin-top: 10px; }
.ub-sub    { padding: 7px 16px; background: #0d1b3e; color: white; border: none; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; }
.ub-cancel { padding: 7px 12px; background: #f1f5f9; color: #64748b; border: none; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; }

/* GRADE */
.grade-hero { text-align: center; padding: 20px 0 16px; border-bottom: 1px solid #f1f5f9; margin-bottom: 16px; }
.grade-big  { font-size: 52px; font-weight: 800; color: #0d1b3e; line-height: 1; }
.grade-lbl  { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: .07em; margin-top: 4px; }

@media(max-width: 900px) {
    .sidebar { display: none; }
    .main { margin-left: 0; }
    .stats { grid-template-columns: 1fr 1fr; }
    .qgrid { grid-template-columns: 1fr 1fr; }
    .two-col { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sb-brand">
        <div class="sb-logo">&#127979;</div>
        <div>
            <div class="sb-name">Kabarak University</div>
            <div class="sb-sub">Online Admission System</div>
        </div>
    </div>

    <div style="flex:1; padding: 8px 0;">
        <div class="sb-section">Main Menu</div>

        <button class="sb-item active" id="btn-dashboard" onclick="showTab('dashboard')">
            <span class="si">&#x229E;</span> Dashboard
        </button>

        <button class="sb-item" id="btn-documents" onclick="showTab('documents')">
            <span class="si">&#128206;</span> My Documents
            <?php
            $bg  = $docCount < $totalDocs ? '#fef3c7' : '#dcfce7';
            $clr = $docCount < $totalDocs ? '#92400e' : '#166534';
            echo '<span class="sbbadge" style="background: - status.php:322' . $bg . ';color:' . $clr . ';">' . $docCount . '/' . $totalDocs . '</span>';
            ?>
        </button>

        <button class="sb-item" id="btn-profile" onclick="showTab('profile')">
            <span class="si">&#128100;</span> My Profile
        </button>

        <button class="sb-item" id="btn-academic" onclick="showTab('academic')">
            <span class="si">&#127979;</span> Academic Info
        </button>

        <div class="sb-section" style="margin-top: 8px;">Support</div>
        <button class="sb-item" onclick="alert('For help contact:\nadmissions@kabarak.ac.ke\n+254 700 000 000')">
            <span class="si">&#10067;</span> Help &amp; FAQs
        </button>
    </div>

    <div class="sb-footer">
        <div class="sb-user">
            <div class="sbava - status.php:342"><?php echo initials($fullName); ?></div>
            <div>
                <div class="sbuname - status.php:344"><?php echo h($firstName); ?></div>
                <div class="sb-urole">Student Applicant</div>
            </div>
        </div>
        <a href="logout.php" class="sb-out">Sign Out</a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <header class="topbar">
        <div>
            <div class="tb-title" id="page-title">Dashboard</div>
            <div class="tb-sub">Kabarak University &mdash; Student Admission Portal</div>
        </div>
        <?php if ($app): ?>
        <span class="chip <?php echo $chipClass; ?> - status.php:360"><?php echo $chipLabel; ?></span>
        <?php endif; ?>
    </header>

    <div class="page">

        <?php if ($f): ?>
        <div class="<?php echo $f['type']==='success' ? 'flashok' : 'flasher'; ?> - status.php:367">
            <?php echo h($f['message - status.php:368']); ?>
        </div>
        <?php endif; ?>

        <?php if (!$app): ?>
        <!-- NO APPLICATION -->
        <div style="max-width:480px;margin:40px auto;background:white;border-radius:14px;border:1px solid #e5eaf2;text-align:center;padding:48px 32px;">
            <div style="font-size:48px;margin-bottom:14px;">&#128221;</div>
            <div style="font-size:16px;font-weight:700;color:#0d1b3e;margin-bottom:8px;">No Application Found</div>
            <div style="font-size:13px;color:#94a3b8;margin-bottom:24px;line-height:1.6;">You have not submitted an application yet.</div>
            <a href="apply.php" style="display:inline-block;background:#0d1b3e;color:white;padding:11px 28px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:600;">Start My Application &rarr;</a>
        </div>

        <?php else: ?>

        <!-- ===== TAB: DASHBOARD ===== -->
        <div id="tab-dashboard" class="tab-content active">

            <?php
            if ($status === 'Approved') {
                $hClass = 'hero-approved';
                $hDeco  = '&#127891;';
                $hTitle = 'Congratulations, ' . h($firstName) . '!';
                $hText  = 'Your application for <strong>' . h($app['program']) . '</strong> has been approved for the <strong>' . h($app['intake']) . '</strong> intake. Please report to campus with all original documents.';
            } elseif ($status === 'Rejected') {
                $hClass = 'hero-rejected';
                $hDeco  = '&#128203;';
                $hTitle = 'Application Update';
                $hText  = 'Unfortunately your application was not successful at this time. Please contact the admissions office for guidance on next steps.';
            } else {
                $hClass = 'hero-pending';
                $hDeco  = '&#9203;';
                $hTitle = 'Welcome back, ' . h($firstName) . '!';
                $hText  = 'Your application has been received and is currently being reviewed by the admissions team. This page refreshes automatically every 20 seconds.';
            }
            ?>

            <div class="hero <?php echo $hClass; ?> - status.php:405">
                <div class="herodeco - status.php:406"><?php echo $hDeco; ?></div>
                <h2><?php echo $hTitle; ?></h2>
                <p><?php echo $hText; ?></p>
                <?php if ($status === 'Approved'): ?>
                <div class="herotag - status.php:410">&#128197; Intake: <?php echo h($app['intake']); ?></div>
                <?php endif; ?>
            </div>

            <?php if (!empty($adminComment)): ?>
            <div class="comment">
                <div class="c-lbl">&#128172; Message from Admissions Office</div>
                <div class="ctxt - status.php:417"><?php echo h($adminComment); ?></div>
            </div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat">
                    <div class="stat-ico ic-b">&#128203;</div>
                    <div><div class="sv - status.php:424">#<?php echo $app['id']; ?></div><div class="sl">Application ID</div></div>
                </div>
                <div class="stat">
                    <div class="stat-ico ic-g">&#127979;</div>
                    <div><div class="sv - status.php:428"><?php echo h($app['kcse_grade']); ?></div><div class="sl">KCSE Grade</div></div>
                </div>
                <div class="stat">
                    <div class="stat-ico ic-gr">&#128206;</div>
                    <div><div class="sv - status.php:432"><?php echo $docCount . '/' . $totalDocs; ?></div><div class="sl">Docs Uploaded</div></div>
                </div>
            </div>

            <div class="qgrid">
                <div class="qlink" onclick="showTab('documents')">
                    <div class="qlink-ico">&#128206;</div>
                    <div class="qlink-title">My Documents</div>
                    <div class="qlinksub - status.php:440"><?php echo $docCount; ?> of <?php echo $totalDocs; ?> uploaded</div>
                </div>
                <div class="qlink" onclick="showTab('profile')">
                    <div class="qlink-ico">&#128100;</div>
                    <div class="qlink-title">My Profile</div>
                    <div class="qlink-sub">View personal details</div>
                </div>
                <div class="qlink" onclick="showTab('academic')">
                    <div class="qlink-ico">&#127979;</div>
                    <div class="qlink-title">Academic Info</div>
                    <div class="qlink-sub">Grades &amp; programme</div>
                </div>
                <div class="qlink" onclick="alert('Admissions Office\nadmissions@kabarak.ac.ke\n+254 700 000 000\nMon-Fri 8am-5pm')">
                    <div class="qlink-ico">&#128222;</div>
                    <div class="qlink-title">Contact Us</div>
                    <div class="qlink-sub">Admissions office</div>
                </div>
            </div>

            <div class="two-col">
                <div>
                    <div class="card">
                        <div class="card-head"><div class="card-title">&#128203; Quick Summary</div></div>
                        <div class="card-body">
                            <div class="ir - status.php:464"><span class="ik">Programme</span><span class="iv" style="font-size:12px;"><?php echo h($app['program']); ?></span></div>
                            <div class="ir - status.php:465"><span class="ik">Intake</span><span class="iv"><?php echo h($app['intake']); ?></span></div>
                            <div class="ir - status.php:466"><span class="ik">Status</span><span class="iv"><span class="chip <?php echo $chipClass; ?>"><?php echo $status; ?></span></span></div>
                            <div class="ir - status.php:467"><span class="ik">Submitted</span><span class="iv" style="font-size:12px;"><?php echo date('d M Y, g:i A', strtotime($app['submitted_at'])); ?></span></div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="card">
                        <div class="card-head"><div class="card-title">&#128506; Progress Tracker</div></div>
                        <div class="card-body">
                            <div class="step"><div class="step-dot sd-done">&#10003;</div><div class="step-info"><strong>Account Created</strong><span>Registration complete</span></div></div>
                            <div class="step"><div class="step-dot sd-done">&#10003;</div><div class="step-info"><strong>Application Submitted</strong><span>Received by admissions</span></div></div>
                            <div class="step">
                                <?php if ($status === 'Pending'): ?>
                                <div class="step-dot sd-act">&#9203;</div><div class="step-info"><strong>Under Review</strong><span>Awaiting decision...</span></div>
                                <?php elseif ($status === 'Approved'): ?>
                                <div class="step-dot sd-done">&#10003;</div><div class="step-info"><strong>Decision Made</strong><span style="color:#166534;">Approved!</span></div>
                                <?php else: ?>
                                <div class="step-dot sd-fail">&#10005;</div><div class="step-info"><strong>Decision Made</strong><span style="color:#991b1b;">Not successful</span></div>
                                <?php endif; ?>
                            </div>
                            <div class="step">
                                <?php if ($status === 'Approved'): ?>
                                <div class="step-dot sd-done">&#10003;</div><div class="step-info"><strong>Enrollment</strong><span style="color:#166534;">Report to campus</span></div>
                                <?php else: ?>
                                <div class="step-dot sd-wait">4</div><div class="step-info"><strong>Enrollment</strong><span>Awaiting approval</span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-head"><div class="card-title">&#128222; Admissions Office</div></div>
                        <div class="card-body">
                            <div class="citem"><div class="ci-ico">&#128231;</div>admissions@kabarak.ac.ke</div>
                            <div class="citem"><div class="ci-ico">&#128241;</div>+254 700 000 000</div>
                            <div class="citem"><div class="ci-ico">&#128336;</div>Mon &ndash; Fri, 8am &ndash; 5pm</div>
                            <div class="citem"><div class="ci-ico">&#128205;</div>Admissions Block, Main Campus</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== TAB: DOCUMENTS ===== -->
        <div id="tab-documents" class="tab-content">
            <div class="card">
                <div class="card-head">
                    <div class="card-title">&#128206; My Documents</div>
                    <div class="cardbadge - status.php:513"><?php echo $docCount; ?> / <?php echo $totalDocs; ?> uploaded</div>
                </div>
                <div class="card-body">
                    <p style="font-size:12px;color:#94a3b8;margin-bottom:18px;line-height:1.6;">Upload your supporting documents. Click <strong style="color:#0d1b3e;">Upload</strong> to add or <strong style="color:#0d1b3e;">Replace</strong> to update. Max 2MB per file (PDF, JPG, PNG).</p>

                    <?php foreach ($docSlots as $field => $label):
                        $ex = isset($docsByType[$field]) ? $docsByType[$field] : null;
                    ?>
                    <div class="docrow <?php echo $ex ? 'hasdoc' : ''; ?> - status.php:521">
                        <div class="docico - status.php:522"><?php echo $ex ? '&#9989;' : '&#128196;'; ?></div>
                        <div class="doc-inf">
                            <div class="docnm - status.php:524"><?php echo $label; ?></div>
                            <?php if ($ex): ?>
                            <div class="docfn - status.php:526">&#128206; <?php echo h($ex['file_name']); ?> &bull; <?php echo date('d M Y', strtotime($ex['uploaded_at'])); ?></div>
                            <?php else: ?>
                            <div class="doc-fn" style="color:#f59e0b;">&#9888;&#65039; Not uploaded yet</div>
                            <?php endif; ?>
                        </div>
                        <div class="doc-act">
                            <?php if ($ex): ?>
                            <button class="dbtn dbrep - status.php:533" onclick="openPanel('<?php echo $field; ?>')">&#x1F504; Replace</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this document?');">
                                <input type="hidden" name="action" value="delete_doc">
                                <input type="hidden - status.php:536" name="doc_id" value="<?php echo $ex['id']; ?>">
                                <button type="submit" class="dbtn db-del">&#128465;</button>
                            </form>
                            <?php else: ?>
                            <button class="dbtn dbup - status.php:540" onclick="openPanel('<?php echo $field; ?>')">&#8679; Upload</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="upanel - status.php:544" id="panel_<?php echo $field; ?>">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_doc">
                            <input type="hidden - status.php:547" name="doc_type" value="<?php echo h($label); ?>">
                            <input type="hidden - status.php:548" name="doc_id" value="<?php echo $ex ? $ex['id'] : 0; ?>">
                            <div class="dropzone">
                                <input type="file - status.php:550" name="doc_file" accept=".pdf,.jpg,.jpeg,.png" required onchange="previewFile(this,'<?php echo $field; ?>')">
                                <div class="dz-ico">&#128194;</div>
                                <div class="dz-txt"><strong>Click to browse files</strong> or drag &amp; drop</div>
                                <div class="dz-txt">PDF, JPG or PNG &mdash; Maximum 2MB</div>
                            </div>
                            <div class="fileok - status.php:555" id="prev_<?php echo $field; ?>"></div>
                            <div class="up-btns">
                                <button type="submit" class="ub-sub">&#9989; Upload Now</button>
                                <button type="button - status.php:558" class="ub-cancel" onclick="closePanel('<?php echo $field; ?>')">Cancel</button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ===== TAB: PROFILE ===== -->
        <div id="tab-profile" class="tab-content">
            <div class="card">
                <div class="card-head"><div class="card-title">&#128100; Personal Information</div></div>
                <div class="card-body">
                    <div class="ir - status.php:572"><span class="ik">Full Name</span><span class="iv"><?php echo h($app['first_name'] . ' ' . $app['last_name']); ?></span></div>
                    <div class="ir - status.php:573"><span class="ik">Date of Birth</span><span class="iv"><?php echo date('d M Y', strtotime($app['dob'])); ?></span></div>
                    <div class="ir - status.php:574"><span class="ik">Gender</span><span class="iv"><?php echo h($app['gender']); ?></span></div>
                    <div class="ir - status.php:575"><span class="ik">Nationality</span><span class="iv"><?php echo h($app['nationality']); ?></span></div>
                    <div class="ir - status.php:576"><span class="ik">ID / BC Number</span><span class="iv"><?php echo h(!empty($app['id_number']) ? $app['id_number'] : '&mdash;'); ?></span></div>
                    <div class="ir - status.php:577"><span class="ik">Postal Address</span><span class="iv"><?php echo h(!empty($app['address']) ? $app['address'] : '&mdash;'); ?></span></div>
                    <div class="ir - status.php:578"><span class="ik">Email</span><span class="iv"><?php echo h($app['email']); ?></span></div>
                    <div class="ir - status.php:579"><span class="ik">Phone</span><span class="iv"><?php echo h($app['phone']); ?></span></div>
                    <?php if (!empty($app['guardian_name'])): ?>
                    <div style="padding-top:14px;margin-top:6px;border-top:1px solid #f1f5f9;">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:12px;">Parent / Guardian</div>
                        <div class="ir - status.php:583"><span class="ik">Name</span><span class="iv"><?php echo h($app['guardian_name']); ?></span></div>
                        <div class="ir - status.php:584"><span class="ik">Phone</span><span class="iv"><?php echo h($app['guardian_phone']); ?></span></div>
                        <div class="ir - status.php:585"><span class="ik">Relationship</span><span class="iv"><?php echo h($app['guardian_rel']); ?></span></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== TAB: ACADEMIC ===== -->
        <div id="tab-academic" class="tab-content">
            <div class="card">
                <div class="card-head"><div class="card-title">&#127979; Academic &amp; Programme Details</div></div>
                <div class="card-body">
                    <div class="grade-hero">
                        <div class="gradebig - status.php:598"><?php echo h($app['kcse_grade']); ?></div>
                        <div class="grade-lbl">KCSE Overall Grade</div>
                    </div>
                    <div class="ir - status.php:601"><span class="ik">Secondary School</span><span class="iv"><?php echo h($app['school_name']); ?></span></div>
                    <div class="ir - status.php:602"><span class="ik">KCSE Year</span><span class="iv"><?php echo h($app['kcse_year']); ?></span></div>
                    <div class="ir - status.php:603"><span class="ik">Programme Applied</span><span class="iv" style="font-size:12px;max-width:55%;"><?php echo h($app['program']); ?></span></div>
                    <div class="ir - status.php:604"><span class="ik">Intake</span><span class="iv"><?php echo h($app['intake']); ?></span></div>
                    <div class="ir - status.php:605"><span class="ik">Status</span><span class="iv"><span class="chip <?php echo $chipClass; ?>"><?php echo $status; ?></span></span></div>
                    <div class="ir - status.php:606"><span class="ik">Date Submitted</span><span class="iv" style="font-size:12px;"><?php echo date('d M Y, g:i A', strtotime($app['submitted_at'])); ?></span></div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
var titles = {
    'dashboard': 'Dashboard',
    'documents': 'My Documents',
    'profile':   'My Profile',
    'academic':  'Academic Info'
};

function showTab(name) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(function(t) {
        t.classList.remove('active');
    });
    // Deactivate all sidebar buttons
    document.querySelectorAll('.sb-item').forEach(function(b) {
        b.classList.remove('active');
    });
    // Show selected tab
    var tab = document.getElementById('tab-' + name);
    if (tab) tab.classList.add('active');
    // Activate sidebar button
    var btn = document.getElementById('btn-' + name);
    if (btn) btn.classList.add('active');
    // Update page title
    document.getElementById('page-title').textContent = titles[name] || 'Dashboard';
    // Scroll to top
    window.scrollTo(0, 0);
}

function openPanel(f) {
    document.querySelectorAll('.upanel.open').forEach(function(p) { p.classList.remove('open'); });
    var p = document.getElementById('panel_' + f);
    if (p) {
        p.classList.add('open');
        setTimeout(function() { p.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 50);
    }
}
function closePanel(f) {
    var p = document.getElementById('panel_' + f);
    if (p) p.classList.remove('open');
}
function previewFile(input, f) {
    var el = document.getElementById('prev_' + f);
    if (!el || !input.files[0]) return;
    var size = (input.files[0].size / 1048576).toFixed(2);
    if (input.files[0].size > 2097152) {
        el.style.cssText = 'display:block;background:#fff1f2;color:#dc2626;border-radius:6px;';
        el.textContent = 'File too large (' + size + 'MB). Max allowed is 2MB.';
        input.value = '';
    } else {
        el.style.cssText = 'display:block;background:#f0fdf4;color:#059669;border-radius:6px;';
        el.textContent = 'Ready: ' + input.files[0].name + ' (' + size + ' MB)';
    }
}
document.querySelectorAll('.dropzone').forEach(function(z) {
    z.addEventListener('dragover', function(e) { e.preventDefault(); z.style.borderColor = '#4f46e5'; z.style.background = '#eef2ff'; });
    z.addEventListener('dragleave', function() { z.style.borderColor = '#c7d2fe'; z.style.background = '#f8faff'; });
    z.addEventListener('drop', function() { z.style.borderColor = '#c7d2fe'; z.style.background = '#f8faff'; });
});

// Auto refresh every 20 seconds (only reload if on dashboard to get status updates)
setTimeout(function() { window.location.reload(); }, 20000);
</script>
</body>
</html>

<?php
require_once 'config.php';
requireLogin('student');

$userId = $_SESSION['user_id'];

// Check if application already exists
$existing = $conn->prepare("SELECT id, status FROM applications WHERE user_id = ?");
$existing->bind_param('i', $userId);
$existing->execute();
$existing->store_result();

if ($existing->num_rows > 0) {
    redirect('/status.php');
}
$existing->close();

$errors = [];
$data   = [];

$programs = [
    'Diploma in Information Technology',
    'Diploma in Computer Science',
    'Diploma in Business Administration',
    'Diploma in Accounting',
    'Diploma in Nursing',
    'Diploma in Education (Arts)',
    'Diploma in Education (Science)',
    'Diploma in Community Development',
    'Diploma in Journalism & Mass Communication',
    'Diploma in Electrical Engineering',
];

$grades = ['A','A-','B+','B','B-','C+','C','C-','D+','D','D-','E'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array_map('sanitize', [
        'first_name'    => $_POST['first_name']    ?? '',
        'last_name'     => $_POST['last_name']      ?? '',
        'dob'           => $_POST['dob']            ?? '',
        'gender'        => $_POST['gender']         ?? '',
        'nationality'   => $_POST['nationality']    ?? 'Kenyan',
        'id_number'     => $_POST['id_number']      ?? '',
        'address'       => $_POST['address']        ?? '',
        'school_name'   => $_POST['school_name']    ?? '',
        'kcse_year'     => $_POST['kcse_year']      ?? '',
        'kcse_grade'    => $_POST['kcse_grade']     ?? '',
        'program'       => $_POST['program']        ?? '',
        'intake'        => $_POST['intake']         ?? 'September 2026',
        'guardian_name' => $_POST['guardian_name']  ?? '',
        'guardian_phone'=> $_POST['guardian_phone'] ?? '',
        'guardian_rel'  => $_POST['guardian_rel']   ?? '',
    ]);

    // Validation
    if (empty($data['first_name']))  $errors[] = 'First name is required.';
    if (empty($data['last_name']))   $errors[] = 'Last name is required.';
    if (empty($data['dob']))         $errors[] = 'Date of birth is required.';
    if (empty($data['gender']))      $errors[] = 'Gender is required.';
    if (empty($data['school_name'])) $errors[] = 'Secondary school name is required.';
    if (empty($data['kcse_year']))   $errors[] = 'KCSE year is required.';
    if (empty($data['kcse_grade']))  $errors[] = 'KCSE grade is required.';
    if (empty($data['program']))     $errors[] = 'Programme of study is required.';

    if (empty($errors)) {
        // Handle file uploads
        $uploadDir = __DIR__ . '/uploads/';
        $docs = [];
        $uploadOk = true;

        $fileFields = ['cert' => 'KCSE Certificate', 'id_doc' => 'National ID / Birth Cert', 'photo' => 'Passport Photo'];
        foreach ($fileFields as $field => $label) {
            if (!empty($_FILES[$field]['name'])) {
                $ext  = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf','jpg','jpeg','png'];
                if (!in_array($ext, $allowed)) {
                    $errors[] = "$label must be PDF, JPG, or PNG.";
                    $uploadOk = false;
                } elseif ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
                    $errors[] = "$label file size must not exceed 2MB.";
                    $uploadOk = false;
                } else {
                    $newName = uniqid($field . '_') . '.' . $ext;
                    if (move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $newName)) {
                        $docs[] = ['type' => $label, 'name' => $_FILES[$field]['name'], 'path' => $newName];
                    }
                }
            }
        }

        if ($uploadOk && empty($errors)) {
            $stmt = $conn->prepare("
                INSERT INTO applications
                (user_id, first_name, last_name, dob, gender, nationality, id_number, address,
                 school_name, kcse_year, kcse_grade, program, intake,
                 guardian_name, guardian_phone, guardian_rel)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param(
             'isssssssssssssss',
               $userId,
                $data['first_name'], $data['last_name'], $data['dob'],
                $data['gender'], $data['nationality'], $data['id_number'], $data['address'],
                $data['school_name'], $data['kcse_year'], $data['kcse_grade'],
                $data['program'], $data['intake'],
                $data['guardian_name'], $data['guardian_phone'], $data['guardian_rel']
            );

            if ($stmt->execute()) {
                $appId = $stmt->insert_id;

                // Save document records
                foreach ($docs as $doc) {
                    $ds = $conn->prepare("INSERT INTO documents (application_id, doc_type, file_name, file_path) VALUES (?,?,?,?)");
                    $ds->bind_param('isss', $appId, $doc['type'], $doc['name'], $doc['path']);
                    $ds->execute();
                    $ds->close();
                }

                flash('success', 'Application submitted successfully! We will review it shortly.');
                redirect('/status.php');
            } else {
                $errors[] = 'Submission failed. Please try again.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply — OSAS | Kabarak University</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<!-- TOPBAR -->
<header class="topbar">
    <a href="/apply.php" class="topbar-brand">
        <div class="topbar-logo">KU</div>
        <div>
            <div class="topbar-title">OSAS</div>
            <div class="topbar-subtitle">Kabarak University</div>
        </div>
    </a>
    <nav class="topbar-nav">
        <div class="topbar-user">
            <div class="topbar-avatar"><?= initials($_SESSION['full_name']) ?></div>
            <?= h($_SESSION['full_name']) ?>
        </div>
        <a href="/status.php">My Status</a>
        <a href="/logout.php" class="btn-logout">Sign Out</a>
    </nav>
</header>

<main class="main">
    <div class="page-header">
        <div class="breadcrumb">Home &rsaquo; Application Form</div>
        <h2>📋 Student Application Form</h2>
        <p>Complete all sections carefully. Fields marked with * are required.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            ❌ <strong>Please correct the following errors:</strong>
            <ul style="margin:8px 0 0 16px;">
                <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" novalidate>

        <!-- SECTION 1: PERSONAL INFORMATION -->
        <div class="form-section">
            <div class="form-section-header">👤 1. Personal Information</div>
            <div class="form-section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" placeholder="e.g. Ruth"
                               value="<?= h($data['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" placeholder="e.g. Jebet"
                               value="<?= h($data['last_name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <input type="date" name="dob" value="<?= h($data['dob'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" required>
                            <option value="">-- Select --</option>
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($data['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" name="nationality" placeholder="Kenyan"
                               value="<?= h($data['nationality'] ?? 'Kenyan') ?>">
                    </div>
                    <div class="form-group">
                        <label>National ID / Birth Cert. No.</label>
                        <input type="text" name="id_number" placeholder="e.g. 12345678"
                               value="<?= h($data['id_number'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Postal Address</label>
                    <input type="text" name="address" placeholder="e.g. P.O. Box 100, Nakuru"
                           value="<?= h($data['address'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- SECTION 2: ACADEMIC BACKGROUND -->
        <div class="form-section">
            <div class="form-section-header">🎓 2. Academic Background</div>
            <div class="form-section-body">
                <div class="form-group">
                    <label>Secondary School Name *</label>
                    <input type="text" name="school_name" placeholder="e.g. Nakuru High School"
                           value="<?= h($data['school_name'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>KCSE Year *</label>
                        <select name="kcse_year" required>
                            <option value="">-- Select Year --</option>
                            <?php for ($y = 2026; $y >= 2015; $y--): ?>
                                <option value="<?= $y ?>" <?= ($data['kcse_year'] ?? '') == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>KCSE Overall Grade *</label>
                        <select name="kcse_grade" required>
                            <option value="">-- Select Grade --</option>
                            <?php foreach ($grades as $g): ?>
                                <option value="<?= $g ?>" <?= ($data['kcse_grade'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: PROGRAMME -->
        <div class="form-section">
            <div class="form-section-header">📚 3. Programme of Study</div>
            <div class="form-section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Preferred Programme *</label>
                        <select name="program" required>
                            <option value="">-- Select Programme --</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= h($p) ?>" <?= ($data['program'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Intake</label>
                        <select name="intake">
                            <option value="September 2026" <?= ($data['intake'] ?? '') === 'September 2026' ? 'selected' : '' ?>>September 2026</option>
                            <option value="January 2027">January 2027</option>
                            <option value="May 2027">May 2027</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: PARENT/GUARDIAN -->
        <div class="form-section">
            <div class="form-section-header">👨‍👩‍👦 4. Parent / Guardian Information</div>
            <div class="form-section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Guardian Full Name</label>
                        <input type="text" name="guardian_name" placeholder="Full name"
                               value="<?= h($data['guardian_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Guardian Phone</label>
                        <input type="tel" name="guardian_phone" placeholder="07XXXXXXXX"
                               value="<?= h($data['guardian_phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Relationship to Applicant</label>
                    <select name="guardian_rel">
                        <option value="">-- Select --</option>
                        <?php foreach (['Father','Mother','Guardian','Sibling','Spouse','Other'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($data['guardian_rel'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- SECTION 5: DOCUMENTS -->
        <div class="form-section">
            <div class="form-section-header">📎 5. Supporting Documents <span style="font-weight:400;opacity:.75;">(PDF/JPG/PNG — max 2MB each)</span></div>
            <div class="form-section-body">
                <div class="form-row" style="grid-template-columns:repeat(3,1fr);">
                    <div class="form-group">
                        <label>KCSE Certificate</label>
                        <label class="upload-zone" id="z1">
                            <input type="file" name="cert" accept=".pdf,.jpg,.jpeg,.png" onchange="showFile(this,'p1','z1')">
                            <div class="upload-zone-icon">📄</div>
                            <div class="upload-zone-text"><span>Choose file</span> or drag here</div>
                            <div class="file-preview" id="p1"></div>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>National ID / Birth Certificate</label>
                        <label class="upload-zone" id="z2">
                            <input type="file" name="id_doc" accept=".pdf,.jpg,.jpeg,.png" onchange="showFile(this,'p2','z2')">
                            <div class="upload-zone-icon">🪪</div>
                            <div class="upload-zone-text"><span>Choose file</span> or drag here</div>
                            <div class="file-preview" id="p2"></div>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Passport Photo</label>
                        <label class="upload-zone" id="z3">
                            <input type="file" name="photo" accept=".jpg,.jpeg,.png" onchange="showFile(this,'p3','z3')">
                            <div class="upload-zone-icon">🖼️</div>
                            <div class="upload-zone-text"><span>Choose file</span> or drag here</div>
                            <div class="file-preview" id="p3"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- DECLARATION -->
        <div class="form-section">
            <div class="form-section-header">✍️ Declaration</div>
            <div class="form-section-body">
                <div class="form-group" style="display:flex;align-items:flex-start;gap:10px;">
                    <input type="checkbox" id="agree" name="agree" required
                           style="width:auto;margin-top:4px;accent-color:var(--primary);">
                    <label for="agree" style="text-transform:none;font-size:0.88rem;font-weight:400;color:var(--text);">
                        I declare that all information I have provided in this application is accurate and
                        complete to the best of my knowledge. I understand that providing false information
                        may lead to disqualification of my application.
                    </label>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1rem;">
            <a href="/index.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-gold btn-lg">
                🚀 Submit Application
            </button>
        </div>

    </form>
</main>

<script>
function showFile(input, previewId, zoneId) {
    const file = input.files[0];
    if (file) {
        const size = (file.size / 1024 / 1024).toFixed(2);
        document.getElementById(previewId).textContent = '✅ ' + file.name + ' (' + size + ' MB)';
        document.getElementById(zoneId).style.borderColor = 'var(--success)';
        if (size > 2) {
            document.getElementById(previewId).textContent = '❌ File too large (max 2MB)';
            document.getElementById(previewId).style.color = 'var(--danger)';
        }
    }
}
// Drag-and-drop visual feedback
document.querySelectorAll('.upload-zone').forEach(zone => {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', () => zone.classList.remove('drag-over'));
});
// Checkbox validation
document.querySelector('form').addEventListener('submit', function(e) {
    if (!document.getElementById('agree').checked) {
        e.preventDefault();
        alert('Please read and accept the declaration before submitting.');
    }
});
</script>
</body>
</html>

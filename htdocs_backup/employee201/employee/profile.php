<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
include '../includes/header.php';

// ✅ Redirect if not logged in as employee
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$employee_code  = $_SESSION['employee_code'];
$upload_message = '';
$upload_type    = ''; // 'success' or 'error'

// Escape helper
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ══ Handle profile photo upload (POST) ════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['id_picture'])) {
    $file         = $_FILES['id_picture'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize      = 5 * 1024 * 1024; // 5 MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_message = 'Upload failed. Please try again.';
        $upload_type    = 'error';
    } elseif (!in_array($file['type'], $allowedTypes)) {
        $upload_message = 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.';
        $upload_type    = 'error';
    } elseif ($file['size'] > $maxSize) {
        $upload_message = 'File too large. Maximum size is 5MB.';
        $upload_type    = 'error';
    } else {
        $uploadDir = __DIR__ . '/../uploads/id_picture/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'emp_' . $employee_code . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Delete old photo
            $oldStmt = $conn->prepare("SELECT id_picture FROM employees WHERE employee_code = ?");
            $oldStmt->bind_param("s", $employee_code);
            $oldStmt->execute();
            $oldStmt->bind_result($oldPic);
            $oldStmt->fetch();
            $oldStmt->close();
            if ($oldPic && $oldPic !== 'default.png' && file_exists($uploadDir . basename($oldPic))) {
                unlink($uploadDir . basename($oldPic));
            }

            // Save new filename
            $upStmt = $conn->prepare("UPDATE employees SET id_picture = ? WHERE employee_code = ?");
            $upStmt->bind_param("ss", $filename, $employee_code);
            $upStmt->execute();
            $upStmt->close();

            $upload_message = 'Profile photo updated successfully!';
            $upload_type    = 'success';
        } else {
            $upload_message = 'Could not save the file. Check folder permissions.';
            $upload_type    = 'error';
        }
    }
}

// ══ Handle 201 document upload (POST) ══════════════════════════════════════════════
$doc_upload_msg  = '';
$doc_upload_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc']) && isset($_FILES['doc_file'])) {
    $docType  = $_POST['document_type'] ?? '';
    $docFile  = $_FILES['doc_file'];
    $allowedDocTypes = ['application/pdf','image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    $maxDocSize      = 10 * 1024 * 1024; // 10 MB

    if (empty($docType)) {
        $doc_upload_msg  = 'Please select a document type.';
        $doc_upload_type = 'error';
    } elseif ($docFile['error'] !== UPLOAD_ERR_OK) {
        $doc_upload_msg  = 'File upload failed. Please try again.';
        $doc_upload_type = 'error';
    } elseif (!in_array($docFile['type'], $allowedDocTypes)) {
        $doc_upload_msg  = 'Invalid file type. PDF, JPG, PNG, GIF, WEBP allowed.';
        $doc_upload_type = 'error';
    } elseif ($docFile['size'] > $maxDocSize) {
        $doc_upload_msg  = 'File too large. Maximum size is 10MB.';
        $doc_upload_type = 'error';
    } else {
        $docUploadDir = __DIR__ . '/../uploads/documents/';
        if (!is_dir($docUploadDir)) mkdir($docUploadDir, 0755, true);
        $ext      = strtolower(pathinfo($docFile['name'], PATHINFO_EXTENSION));
        $filename = 'doc_' . $employee_code . '_' . $docType . '_' . time() . '.' . $ext;
        $destPath = $docUploadDir . $filename;

        if (move_uploaded_file($docFile['tmp_name'], $destPath)) {
            $empIdStmt = $conn->prepare("SELECT id FROM employees WHERE employee_code = ? LIMIT 1");
            $empIdStmt->bind_param("s", $employee_code);
            $empIdStmt->execute();
            $empIdStmt->bind_result($empId);
            $empIdStmt->fetch();
            $empIdStmt->close();

            // Delete old record for this doc type before inserting new one
            $delDoc = $conn->prepare("DELETE FROM documents WHERE employee_id = ? AND document_type = ?");
            $delDoc->bind_param("is", $empId, $docType);
            $delDoc->execute();
            $delDoc->close();

            // Store relative path
            $filePath = 'uploads/documents/' . $filename;

            $insDoc = $conn->prepare("
                INSERT INTO documents (employee_id, document_type, file_path, filename, uploaded_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $insDoc->bind_param("isss", $empId, $docType, $filePath, $filename);
            if ($insDoc->execute()) {
                $doc_upload_msg  = 'Document uploaded successfully!';
                $doc_upload_type = 'success';
            } else {
                $doc_upload_msg  = 'Database error: ' . $insDoc->error;
                $doc_upload_type = 'error';
            }
            $insDoc->close();
        } else {
            $doc_upload_msg  = 'Could not save the file. Check folder permissions.';
            $doc_upload_type = 'error';
        }
    }
}

// ══ Fetch employee details ════════════════════════════════════════════════════
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_code = ?");
$stmt->bind_param("s", $employee_code);
$stmt->execute();
$result   = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) die("Employee record not found.");

// ══ Fetch credentials from employee_users ════════════════════════════════════
$uStmt = $conn->prepare("SELECT username, plain_password, password AS hashed FROM employee_users WHERE employee_code = ? LIMIT 1");
$uStmt->bind_param("s", $employee_code);
$uStmt->execute();
$uResult     = $uStmt->get_result();
$userAccount = $uResult->fetch_assoc();
$uStmt->close();

$accountUsername = $userAccount['username']       ?? '';
$accountPassword = $userAccount['plain_password'] ?? '';
$accountHashed   = $userAccount['hashed']         ?? '';

// Detect if employee has already changed their password
$passChanged = ($accountPassword !== '' && $accountHashed !== '')
    ? !password_verify($accountPassword, $accountHashed)
    : false;

// ══ Only the 8 required document types ═══════════════════════════════════════
$document_types = [
    'birth_cert' => 'Birth Certificate',
    'tin'        => 'TIN',
    'sss'        => 'SSS',
    'philhealth' => 'PhilHealth',
    'pagibig'    => 'Pag-IBIG',
    'resume'     => 'Resume',
    'contract'   => 'Employment Contract',
    'policy'     => 'Signed Policies',
];

// ══ Fetch 201 documents — latest upload per document type only ════════════════
$doc_stmt = $conn->prepare("
    SELECT d1.*
    FROM documents d1
    INNER JOIN (
        SELECT document_type, MAX(uploaded_at) AS latest
        FROM documents
        WHERE employee_id = ?
        GROUP BY document_type
    ) d2 ON d1.document_type = d2.document_type
        AND d1.uploaded_at    = d2.latest
        AND d1.employee_id    = ?
");
$doc_stmt->bind_param("ii", $employee['id'], $employee['id']);
$doc_stmt->execute();
$docs_result = $doc_stmt->get_result();

$id_picture = $employee['id_picture'] ?? '';
$photo_path = !empty($id_picture)
    ? '../uploads/id_picture/' . basename($id_picture)
    : '../uploads/id_picture/default.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* ── Clickable photo with camera overlay ─────────────────────────────────── */
.photo-wrapper {
    position: relative;
    display: inline-block;
    cursor: pointer;
    flex-shrink: 0;
}
.photo-wrapper img {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #3b3b4a;
    display: block;
    transition: filter 0.2s;
}
.photo-wrapper:hover img { filter: brightness(0.55); }
.photo-overlay {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    opacity: 0;
    transition: opacity 0.2s;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    pointer-events: none;
}
.photo-overlay i { font-size: 20px; }
.photo-wrapper:hover .photo-overlay { opacity: 1; }
#photoFileInput { display: none; }

/* ── Uploading spinner ── */
.upload-spinner {
    display: none;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #9ca3af;
    margin-top: 6px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.spin-icon {
    width: 13px; height: 13px;
    border: 2px solid rgba(255,255,255,0.15);
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: inline-block;
}

/* ── Upload feedback banner ── */
.upload-banner {
    display: none;
    padding: 8px 14px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 8px;
}
.upload-banner.success { background:#14532d; color:#86efac; border:1px solid #16a34a; }
.upload-banner.error   { background:#4c0519; color:#fca5a5; border:1px solid #dc2626; }

/* ── Password status badge ── */
.pass-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 9px;
    border-radius: 20px;
    margin-left: 8px;
    vertical-align: middle;
}
.pass-badge.changed { background:#14532d; color:#86efac; }
.pass-badge.default { background:#1e3a5f; color:#93c5fd; }

/* ── 201 Documents checklist ─────────────────────────────────────────────── */
.doc-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
.doc-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    border-radius: 10px;
    background: #1e1e2d;
    border: 1px solid #2e2e3d;
    transition: border-color 0.2s, background 0.2s;
}
.doc-row.submitted { border-color: #166534; background: #0f2017; }
.doc-row:hover { border-color: #3b82f6; }
.doc-check { flex-shrink: 0; width: 22px; text-align: center; }
.doc-checkbox {
    display: inline-block;
    width: 18px; height: 18px;
    border: 2px solid #4b4b5a;
    border-radius: 4px;
    background: #2b2b3b;
}
.doc-label { flex: 1; font-size: 14px; color: #e5e7eb; font-weight: 500; }
.doc-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.doc-date  { font-size: 12px; color: #6b7280; }
.doc-status {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    white-space: nowrap;
}
.not-yet         { background: #1e1e2d; color: #4b4b5a; border: 1px solid #3b3b4a; }
.submitted-badge { background: #14532d; color: #86efac; }

/* ── Doc upload banner ── */
.doc-banner {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 12px;
}
.doc-banner.success { background:#14532d; color:#86efac; border:1px solid #16a34a; }
.doc-banner.error   { background:#4c0519; color:#fca5a5; border:1px solid #dc2626; }

/* ── Purple upload button ── */
.action-btn.purple {
    background: linear-gradient(90deg, #7c3aed, #a855f7);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px 24px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
    display: block;
}
.action-btn.purple:hover { background: linear-gradient(90deg,#6d28d9,#7c3aed); transform: translateY(-1px); }

/* ── Upload Document Modal ── */
.upload-doc-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.78);
    z-index: 10000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.upload-doc-overlay.active { display: flex; }
.upload-doc-modal {
    background: #1e1e2d;
    border: 1px solid #3b3b4a;
    border-radius: 14px;
    box-shadow: 0 12px 48px rgba(0,0,0,0.7);
    width: 100%;
    max-width: 480px;
    overflow: hidden;
    animation: udmIn 0.25s ease;
}
@keyframes udmIn {
    from { opacity:0; transform:translateY(-14px); }
    to   { opacity:1; transform:translateY(0); }
}
.udm-header {
    padding: 18px 22px;
    border-bottom: 1px solid #3b3b4a;
    background: #232330;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.udm-header h3 { margin:0; font-size:16px; font-weight:700; color:#f9fafb; display:flex; align-items:center; gap:8px; }
.udm-header h3 i { color:#a855f7; }
.udm-close {
    background: none;
    border: none;
    color: #6b7280;
    font-size: 22px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.2s;
}
.udm-close:hover { color: #f9fafb; }
.udm-body { padding: 22px; }
.udm-label { display:block; font-size:12px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px; }
.udm-select {
    width: 100%;
    padding: 10px 14px;
    background: #2b2b3b;
    border: 1px solid #3b3b4a;
    border-radius: 8px;
    color: #f9fafb;
    font-size: 14px;
    outline: none;
}
.udm-select:focus { border-color: #a855f7; }
.udm-hint { color:#f59e0b; font-size:12px; margin-top:5px; }
.udm-dropzone {
    margin-top: 8px;
    border: 2px dashed #3b3b4a;
    border-radius: 10px;
    padding: 32px 20px;
    text-align: center;
    cursor: pointer;
    background: #161622;
    transition: border-color 0.2s, background 0.2s;
    color: #6b7280;
}
.udm-dropzone:hover, .udm-dropzone.dragover {
    border-color: #a855f7;
    background: #1e1832;
    color: #d1d5db;
}
.udm-dropzone i { font-size: 32px; margin-bottom: 10px; display:block; color:#4b4b5a; }
.udm-dropzone.has-file { border-color:#22c55e; background:#0f2017; }
.udm-dropzone.has-file i { color:#22c55e; }
.udm-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}
.udm-btn {
    padding: 10px 22px;
    border-radius: 8px;
    border: none;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s;
}
.udm-cancel { background:#3b3b4a; color:#e5e7eb; }
.udm-cancel:hover { background:#4b4b5a; }
.udm-upload { background:linear-gradient(90deg,#7c3aed,#a855f7); color:#fff; }
.udm-upload:hover { background:linear-gradient(90deg,#6d28d9,#7c3aed); transform:translateY(-1px); }
.udm-upload:disabled { opacity:0.4; cursor:not-allowed; transform:none; }

/* ── Document Preview Modal ──────────────────────────────────────────────── */
.doc-preview-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    z-index: 10001;
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.doc-preview-overlay.active { display: flex; }
.doc-preview-modal {
    background: #1e1e2d;
    border: 1px solid #3b3b4a;
    border-radius: 14px;
    box-shadow: 0 16px 64px rgba(0,0,0,0.8);
    width: 100%;
    max-width: 860px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: udmIn 0.25s ease;
}
.dpm-header {
    padding: 16px 22px;
    border-bottom: 1px solid #3b3b4a;
    background: #232330;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.dpm-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #f9fafb;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dpm-header h3 i { color: #22c55e; }
.dpm-meta { font-size: 12px; color: #6b7280; }
.dpm-close {
    background: none;
    border: none;
    color: #6b7280;
    font-size: 22px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
    margin-left: auto;
}
.dpm-close:hover { color: #f9fafb; }
.dpm-body {
    flex: 1;
    overflow: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #161622;
    min-height: 300px;
}
.dpm-body iframe {
    width: 100%;
    height: 70vh;
    border: none;
}
.dpm-body img {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
    display: block;
}
.dpm-unsupported {
    text-align: center;
    padding: 40px;
    color: #6b7280;
}
.dpm-unsupported i { font-size: 48px; margin-bottom: 14px; display: block; color: #3b3b4a; }
.dpm-footer {
    padding: 12px 22px;
    border-top: 1px solid #3b3b4a;
    background: #232330;
    display: flex;
    align-items: center;
    gap: 10px;
}
.dpm-btn {
    padding: 8px 18px;
    border-radius: 8px;
    border: none;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    text-decoration: none;
}
.dpm-download { background: linear-gradient(90deg,#059669,#10b981); color:#fff; }
.dpm-download:hover { background: linear-gradient(90deg,#047857,#059669); transform:translateY(-1px); }
.dpm-cancel { background: #3b3b4a; color: #e5e7eb; }
.dpm-cancel:hover { background: #4b4b5a; }

/* Make submitted rows show pointer + hover glow */
.doc-row.submitted:hover {
    border-color: #22c55e !important;
    box-shadow: 0 0 0 2px rgba(34,197,94,0.15);
}
.doc-row.submitted .doc-label::after {
    content: ' 👁';
    font-size: 12px;
    opacity: 0;
    transition: opacity 0.2s;
}
.doc-row.submitted:hover .doc-label::after { opacity: 1; }
</style>
</head>
<body>

<div class="container">

    <!-- ══ Profile Header ════════════════════════════════════════════════════ -->
    <div class="profile-header">

        <!-- Photo: click to upload -->
        <div class="photo-wrapper" onclick="document.getElementById('photoFileInput').click()"
             title="Click to change profile photo">
            <img src="<?= e($photo_path) ?>" alt="Profile Photo" id="profilePhoto">
            <div class="photo-overlay">
                <i class="fas fa-camera"></i>
                <span>Change</span>
            </div>
        </div>

        <!-- Hidden upload form (submitted via JS) -->
        <form id="photoForm" method="POST" enctype="multipart/form-data" style="display:none;">
            <input type="file" id="photoFileInput" name="id_picture"
                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
        </form>

        <div class="info">
            <h2><?= e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))) ?></h2>
            <p><strong>Employee ID:</strong> <?= e($employee['employee_code'] ?? '') ?></p>
            <p><strong>Position:</strong> <?= e($employee['position'] ?? '') ?> — <?= e($employee['department'] ?? '') ?></p>
            <p><strong>Status:</strong> <?= e($employee['employment_status'] ?? '') ?></p>

            <?php if (!empty($employee['employment_status'])): ?>
                <?php $es = strtolower($employee['employment_status']); ?>
                <?php if ($es === 'resigned' && !empty($employee['date_resigned'])): ?>
                    <p><strong>Date Resigned:</strong> <?= e($employee['date_resigned']) ?></p>
                <?php elseif ($es === 'retired' && !empty($employee['date_retired'])): ?>
                    <p><strong>Date Retired:</strong> <?= e($employee['date_retired']) ?></p>
                <?php elseif ($es === 'fired' && !empty($employee['date_fired'])): ?>
                    <p><strong>Date Fired:</strong> <?= e($employee['date_fired']) ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Upload status -->
            <div class="upload-spinner" id="uploadSpinner">
                <span class="spin-icon"></span> Uploading photo…
            </div>
            <div class="upload-banner <?= $upload_type ?>" id="uploadBanner"
                 <?= $upload_message ? 'style="display:block;"' : '' ?>>
                <?= $upload_type === 'success' ? '✅' : ($upload_message ? '❌' : '') ?>
                <?= htmlspecialchars($upload_message) ?>
            </div>
        </div>
    </div>

    <!-- ══ Login Credentials ══════════════════════════════════════════════════ -->
    <div class="credentials-box">
        <h3>🔑 Login Credentials</h3>
        <p>
            <strong>Username:</strong>
            <span id="usernameText"><?= e($accountUsername ?: '—') ?></span>
        </p>
        <p class="password-field">
            <strong>Password:</strong>
            <span id="passwordText" data-password="<?= e($accountPassword) ?>">
                <?= $accountPassword ? str_repeat('•', strlen($accountPassword)) : '—' ?>
            </span>
            <?php if ($accountPassword && !$passChanged): ?>
                <button type="button" class="toggle-password" id="togglePassword">Show</button>
            <?php endif; ?>
            <span class="pass-badge <?= $passChanged ? 'changed' : 'default' ?>">
                <i class="fas <?= $passChanged ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                <?= $passChanged ? 'Password Changed' : 'Default Password' ?>
            </span>
        </p>
        <p style="font-size:13px;color:#aaa;">
            <?= $passChanged
                ? 'Your password has been updated. Keep it safe.'
                : 'Please change your password after first login.' ?>
        </p>
    </div>

    <!-- ══ Stats ══════════════════════════════════════════════════════════════ -->
    <div class="stats-grid">
        <div class="stat-card green">
            <h3><?= e($employee['ss_number']        ?? '—') ?></h3>
            <p>SSS Number</p>
        </div>
        <div class="stat-card blue">
            <h3><?= e($employee['tin_number']        ?? '—') ?></h3>
            <p>TIN Number</p>
        </div>
        <div class="stat-card orange">
            <h3><?= e($employee['philhealth_number'] ?? '—') ?></h3>
            <p>PhilHealth Number</p>
        </div>
        <div class="stat-card red">
            <h3><?= e($employee['hmdf_number']       ?? '—') ?></h3>
            <p>HMDF Number</p>
        </div>
    </div>

    <!-- ══ Personal Info ══════════════════════════════════════════════════════ -->
    <div class="card">
        <h3>Personal Information</h3>
        <p><strong>Full Name:</strong> <?= e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))) ?></p>
        <p><strong>Gender:</strong>    <?= e($employee['gender']    ?? '') ?></p>
        <p><strong>Birthdate:</strong> <?= e($employee['birthdate'] ?? '') ?></p>
        <p><strong>Present Address:</strong>   <?= e($employee['present_address']   ?? '') ?></p>
        <p><strong>Permanent Address:</strong> <?= e($employee['permanent_address'] ?? '') ?></p>
    </div>

    <!-- ══ Contact Info ═══════════════════════════════════════════════════════ -->
    <div class="card">
        <h3>Contact Information</h3>
        <p><strong>Email:</strong>             <?= e($employee['email']          ?? '') ?></p>
        <p><strong>Phone:</strong>             <?= e($employee['contact_no']     ?? '') ?></p>
        <p><strong>Emergency Contact:</strong> <?= e($employee['emergency_name'] ?? '') ?></p>
        <p><strong>Emergency Phone:</strong>   <?= e($employee['emergency_no']   ?? '') ?></p>
    </div>

    <!-- ══ Employment Info ════════════════════════════════════════════════════ -->
    <div class="card">
        <h3>Employment Information</h3>
        <p><strong>Position:</strong>          <?= e($employee['position']          ?? '') ?></p>
        <p><strong>Department:</strong>        <?= e($employee['department']        ?? '') ?></p>
        <p><strong>Date Hired:</strong>        <?= e($employee['date_hired']        ?? '') ?></p>
        <p><strong>Employment Status:</strong> <?= e($employee['employment_status'] ?? '') ?></p>
    </div>

    <!-- ══ 201 Documents Checklist ══════════════════════════════════════════════ -->
    <?php
    // Build submitted docs lookup — only for the 8 required types
    $submittedDocs = [];
    while ($doc = $docs_result->fetch_assoc()) {
        $key = $doc['document_type'] ?? $doc['type'] ?? '';
        if (array_key_exists($key, $document_types)) {
            $rawFilename = $doc['filename'] ?? basename($doc['file_path'] ?? '');
            $submittedDocs[$key] = [
                'uploaded_at' => $doc['uploaded_at'] ?? '',
                'filename'    => $rawFilename,
            ];
        }
    }
    ?>
    <div class="card" id="docsCard">
        <h3>201 Documents</h3>
        <?php if (!empty($doc_upload_msg)): ?>
        <div class="doc-banner <?= $doc_upload_type ?>" id="docBanner" style="display:block;">
            <?= $doc_upload_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($doc_upload_msg) ?>
        </div>
        <?php endif; ?>
        <div class="doc-list">
        <?php foreach ($document_types as $key => $label): ?>
            <?php
            $submitted    = isset($submittedDocs[$key]);
            $justFilename = $submitted ? $submittedDocs[$key]['filename'] : '';
            $uploadedDate = $submitted ? date('M d, Y', strtotime($submittedDocs[$key]['uploaded_at'])) : '';
            ?>
            <div class="doc-row <?= $submitted ? 'submitted' : '' ?>" data-type="<?= e($key) ?>"
                 <?php if ($submitted): ?>
                 data-filename="<?= e($justFilename) ?>"
                 data-label="<?= e($label) ?>"
                 data-date="<?= e($uploadedDate) ?>"
                 onclick="openDocPreview(this)"
                 style="cursor:pointer;"
                 <?php endif; ?>>
                <div class="doc-check">
                    <?php if ($submitted): ?>
                        <i class="fas fa-check-circle" style="color:#22c55e;font-size:18px;"></i>
                    <?php else: ?>
                        <span class="doc-checkbox"></span>
                    <?php endif; ?>
                </div>
                <span class="doc-label"><?= e($label) ?></span>
                <div class="doc-right">
                    <?php if ($submitted): ?>
                        <span class="doc-date"><?= e($uploadedDate) ?></span>
                        <span class="doc-status submitted-badge">Submitted</span>
                    <?php else: ?>
                        <span class="doc-status not-yet">Not yet submitted</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- ══ Action Buttons ════════════════════════════════════════════════════ -->
    <div class="employee-actions">
        <div style="display:flex;flex-direction:column;gap:10px;flex:0 0 auto;">
            <button type="button" class="action-btn purple" id="uploadInfoBtn">Upload Information</button>
            <a href="change_password.php" class="action-btn blue">Change Password</a>
        </div>

        <form class="coe-form" action="request_coe.php" method="POST" style="flex:1;">
            <input type="hidden" name="type" value="COE">
            <select name="reason" required style="width:100%;margin-bottom:8px;padding:8px;border-radius:6px;border:1px solid #ccc;">
                <option value="">-- Select Reason --</option>
                <option>New Job Application</option>
                <option>Loan Application</option>
                <option>Credit Card Application</option>
                <option value="Visa/Immigration">Visa / Immigration</option>
                <option value="Government Requirement (SSS, Pag-IBIG, PhilHealth)">Government Requirement</option>
                <option>Scholarship</option>
                <option>Insurance</option>
                <option>Legal Requirement</option>
                <option>Others</option>
            </select>
            <button type="submit" class="action-btn green" style="width:100%;">Request COE</button>
        </form>

        <form class="coe-form" action="request_coe.php" method="POST" style="flex:1;">
            <input type="hidden" name="type" value="COE_BASIC">
            <select name="reason" required style="width:100%;margin-bottom:8px;padding:8px;border-radius:6px;border:1px solid #ccc;">
                <option value="">-- Select Reason --</option>
                <option>New Job Application</option>
                <option>Loan Application</option>
                <option>Credit Card Application</option>
                <option value="Visa/Immigration">Visa / Immigration</option>
                <option value="Government Requirement (SSS, Pag-IBIG, PhilHealth)">Government Requirement</option>
                <option>Scholarship</option>
                <option>Insurance</option>
                <option>Legal Requirement</option>
                <option>Others</option>
            </select>
            <button type="submit" class="action-btn orange" style="width:100%;">Request COE w/ Basic Salary</button>
        </form>
    </div>

    <!-- ══ Document Preview Modal ════════════════════════════════════════════ -->
    <div class="doc-preview-overlay" id="docPreviewModal">
        <div class="doc-preview-modal">
            <div class="dpm-header">
                <h3><i class="fas fa-file-alt"></i> <span id="dpmTitle">Document</span></h3>
                <span class="dpm-meta" id="dpmMeta"></span>
                <button class="dpm-close" onclick="closeDocPreview()">&times;</button>
            </div>
            <div class="dpm-body" id="dpmBody"></div>
            <div class="dpm-footer">
                <a class="dpm-btn dpm-download" id="dpmDownload" href="#" download target="_blank">
                    <i class="fas fa-download"></i> Download
                </a>
                <button class="dpm-btn dpm-cancel" onclick="closeDocPreview()">Close</button>
            </div>
        </div>
    </div>

    <!-- ══ Upload Document Modal ══════════════════════════════════════════════ -->
    <div class="upload-doc-overlay" id="uploadDocModal">
        <div class="upload-doc-modal">
            <div class="udm-header">
                <h3><i class="fas fa-file-upload"></i> Upload 201 Document</h3>
                <button type="button" class="udm-close" onclick="closeDocModal()">&times;</button>
            </div>
            <form class="udm-body" method="POST" enctype="multipart/form-data" id="docUploadForm">
                <input type="hidden" name="upload_doc" value="1">

                <label class="udm-label">Document Type</label>
                <select name="document_type" id="docTypeSelect" required class="udm-select">
                    <option value="">-- Select Document Type --</option>
                    <?php foreach ($document_types as $key => $label): ?>
                    <option value="<?= e($key) ?>"
                        <?= isset($submittedDocs[$key]) ? 'data-submitted="true"' : '' ?>>
                        <?= e($label) ?><?= isset($submittedDocs[$key]) ? ' ✓' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="udm-hint" id="replaceHint" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i> Already submitted. Uploading will replace it.
                </p>

                <label class="udm-label" style="margin-top:14px;">
                    File <span style="color:#6b7280;font-weight:400;">(PDF, JPG, PNG — max 10MB)</span>
                </label>
                <div class="udm-dropzone" id="udmDropzone"
                     onclick="document.getElementById('docFileInput').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p id="dropzoneLabel">Click to browse or drag & drop</p>
                    <input type="file" name="doc_file" id="docFileInput"
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                </div>

                <div class="udm-footer">
                    <button type="button" class="udm-btn udm-cancel" onclick="closeDocModal()">Cancel</button>
                    <button type="submit" class="udm-btn udm-upload" id="udmSubmitBtn" disabled>
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout -->
    <a href="#" class="logout-btn" id="logoutBtn">Logout</a>

    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <h3>Are you sure you want to log out?</h3>
            <div class="modal-actions">
                <button id="confirmLogout" class="btn-confirm">Yes, Logout</button>
                <button id="cancelLogout"  class="btn-cancel">Cancel</button>
            </div>
        </div>
    </div>

</div><!-- /.container -->

<div id="toast" class="toast"></div>

<script>
// ══ Logout modal ══════════════════════════════════════════════════════════════
const logoutBtn     = document.getElementById("logoutBtn");
const logoutModal   = document.getElementById("logoutModal");
const confirmLogout = document.getElementById("confirmLogout");
const cancelLogout  = document.getElementById("cancelLogout");

logoutBtn.addEventListener("click",     e => { e.preventDefault(); logoutModal.style.display = "flex"; });
confirmLogout.addEventListener("click", () => { window.location.href = "../auth/logout.php"; });
cancelLogout.addEventListener("click",  closeLogout);
window.addEventListener("click",        e => { if (e.target === logoutModal) closeLogout(); });
window.addEventListener("keydown",      e => {
    if (logoutModal.style.display === "flex") {
        if (e.key === "Escape") closeLogout();
        if (e.key === "Enter")  confirmLogout.click();
    }
});
function closeLogout() {
    logoutModal.setAttribute("closing","");
    setTimeout(() => { logoutModal.style.display="none"; logoutModal.removeAttribute("closing"); }, 300);
}

// ══ Profile photo: live preview + AJAX upload ═════════════════════════════════
const photoInput    = document.getElementById("photoFileInput");
const photoImg      = document.getElementById("profilePhoto");
const photoForm     = document.getElementById("photoForm");
const uploadSpinner = document.getElementById("uploadSpinner");
const uploadBanner  = document.getElementById("uploadBanner");

photoInput.addEventListener("change", function () {
    if (!this.files || !this.files[0]) return;
    const reader = new FileReader();
    reader.onload = ev => { photoImg.src = ev.target.result; };
    reader.readAsDataURL(this.files[0]);
    uploadBanner.style.display  = "none";
    uploadSpinner.style.display = "flex";
    fetch(window.location.href, { method: "POST", body: new FormData(photoForm) })
        .then(r => r.text())
        .then(() => {
            uploadSpinner.style.display = "none";
            showBanner("✅ Profile photo updated!", "success");
        })
        .catch(() => {
            uploadSpinner.style.display = "none";
            showBanner("❌ Upload failed. Try again.", "error");
            photoImg.src = "<?= e($photo_path) ?>";
        });
});

function showBanner(msg, type) {
    uploadBanner.textContent   = msg;
    uploadBanner.className     = "upload-banner " + type;
    uploadBanner.style.display = "block";
    setTimeout(() => { uploadBanner.style.display = "none"; }, 4000);
}

// ══ Password show/hide toggle ══════════════════════════════════════════════════
const toggleBtn = document.getElementById("togglePassword");
const passSpan  = document.getElementById("passwordText");

if (toggleBtn && passSpan) {
    const plain  = passSpan.getAttribute("data-password") || '';
    const masked = plain ? '•'.repeat(plain.length) : '—';
    let   shown  = false;
    passSpan.textContent = masked;
    toggleBtn.addEventListener("click", () => {
        shown = !shown;
        passSpan.textContent  = shown ? plain  : masked;
        toggleBtn.textContent = shown ? "Hide" : "Show";
    });
}

// ══ COE forms — AJAX + toast ══════════════════════════════════════════════════
const toast = document.getElementById("toast");

document.querySelectorAll(".coe-form").forEach(form => {
    form.addEventListener("submit", function (e) {
        e.preventDefault();
        fetch(this.action, { method: "POST", body: new FormData(this) })
            .then(() => showToast("✅ Request Sent", false))
            .catch(() => showToast("❌ Request Failed", true));
    });
});

function showToast(msg, isError) {
    toast.textContent      = msg;
    toast.style.background = isError ? "#dc3545" : "";
    toast.style.display    = "block";
    toast.classList.add("show");
    setTimeout(() => toast.classList.replace("show","hide"), 2000);
    setTimeout(() => { toast.style.display="none"; toast.classList.remove("hide"); }, 2500);
}

// ══ Upload Document Modal ══════════════════════════════════════════════════════
const uploadInfoBtn  = document.getElementById("uploadInfoBtn");
const uploadDocModal = document.getElementById("uploadDocModal");
const docTypeSelect  = document.getElementById("docTypeSelect");
const replaceHint    = document.getElementById("replaceHint");
const docFileInput   = document.getElementById("docFileInput");
const udmDropzone    = document.getElementById("udmDropzone");
const dropzoneLabel  = document.getElementById("dropzoneLabel");
const udmSubmitBtn   = document.getElementById("udmSubmitBtn");
const docUploadForm  = document.getElementById("docUploadForm");

if (uploadInfoBtn) {
    uploadInfoBtn.addEventListener("click", () => uploadDocModal.classList.add("active"));
}

function closeDocModal() {
    uploadDocModal.classList.remove("active");
    docUploadForm.reset();
    udmDropzone.classList.remove("has-file", "dragover");
    dropzoneLabel.textContent = "Click to browse or drag & drop";
    udmDropzone.querySelector("i").className = "fas fa-cloud-upload-alt";
    replaceHint.style.display = "none";
    udmSubmitBtn.disabled = true;
}

uploadDocModal.addEventListener("click", e => { if (e.target === uploadDocModal) closeDocModal(); });

if (docTypeSelect) {
    docTypeSelect.addEventListener("change", function () {
        const opt = this.options[this.selectedIndex];
        replaceHint.style.display = opt.dataset.submitted ? "block" : "none";
        checkSubmitReady();
    });
}

if (docFileInput) {
    docFileInput.addEventListener("change", function () {
        if (this.files && this.files[0]) {
            dropzoneLabel.textContent = this.files[0].name;
            udmDropzone.classList.add("has-file");
            udmDropzone.querySelector("i").className = "fas fa-file-check";
            checkSubmitReady();
        }
    });
}

if (udmDropzone) {
    udmDropzone.addEventListener("dragover",  e => { e.preventDefault(); udmDropzone.classList.add("dragover"); });
    udmDropzone.addEventListener("dragleave", () => udmDropzone.classList.remove("dragover"));
    udmDropzone.addEventListener("drop", e => {
        e.preventDefault();
        udmDropzone.classList.remove("dragover");
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            docFileInput.files = dt.files;
            dropzoneLabel.textContent = file.name;
            udmDropzone.classList.add("has-file");
            udmDropzone.querySelector("i").className = "fas fa-file-check";
            checkSubmitReady();
        }
    });
}

function checkSubmitReady() {
    const hasType = docTypeSelect && docTypeSelect.value !== "";
    const hasFile = docFileInput  && docFileInput.files && docFileInput.files.length > 0;
    udmSubmitBtn.disabled = !(hasType && hasFile);
}

<?php if ($doc_upload_type === 'success'): ?>
document.addEventListener("DOMContentLoaded", () => {
    const card = document.getElementById("docsCard");
    if (card) card.scrollIntoView({ behavior: "smooth", block: "start" });
    setTimeout(() => {
        const b = document.getElementById("docBanner");
        if (b) b.style.display = "none";
    }, 5000);
});
<?php endif; ?>

// ══ Document Preview Modal ════════════════════════════════════════════════════
const docPreviewModal = document.getElementById("docPreviewModal");
const dpmTitle        = document.getElementById("dpmTitle");
const dpmMeta         = document.getElementById("dpmMeta");
const dpmBody         = document.getElementById("dpmBody");
const dpmDownload     = document.getElementById("dpmDownload");

function openDocPreview(row) {
    const label    = row.dataset.label    || 'Document';
    const filename = row.dataset.filename || '';
    const date     = row.dataset.date     || '';

    if (!filename) return;

    // Always build URL as ../uploads/documents/<filename>
    const url = '../uploads/documents/' + filename;

    dpmTitle.textContent = label;
    dpmMeta.textContent  = date ? 'Submitted: ' + date : '';
    dpmDownload.href     = url;
    dpmDownload.download = filename;

    const ext     = filename.split('.').pop().toLowerCase();
    const imgExts = ['jpg','jpeg','png','gif','webp','bmp'];

    dpmBody.innerHTML = '';

    if (imgExts.includes(ext)) {
        const img = document.createElement('img');
        img.src     = url;
        img.alt     = label;
        img.onerror = () => showUnsupported('Image could not be loaded.');
        dpmBody.appendChild(img);
    } else if (ext === 'pdf') {
        const iframe = document.createElement('iframe');
        iframe.src   = url;
        iframe.title = label;
        dpmBody.appendChild(iframe);
    } else {
        showUnsupported('Preview not available for this file type.<br>Click <strong>Download</strong> to open it.');
    }

    docPreviewModal.classList.add('active');
}

function showUnsupported(msg) {
    dpmBody.innerHTML = `<div class="dpm-unsupported"><i class="fas fa-file"></i><p>${msg}</p></div>`;
}

function closeDocPreview() {
    docPreviewModal.classList.remove('active');
    dpmBody.innerHTML = '';
}

docPreviewModal.addEventListener('click', e => { if (e.target === docPreviewModal) closeDocPreview(); });
window.addEventListener('keydown', e => {
    if (e.key === 'Escape' && docPreviewModal.classList.contains('active')) closeDocPreview();
});
</script>

<?php
$doc_stmt->close();
$conn->close();
?>
</body>
</html>
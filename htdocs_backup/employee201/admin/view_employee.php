<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit;
}
require_once(__DIR__ . '/../auth/session_check.php');
require_once __DIR__ . '/../includes/config.php';
include '../includes/header.php';
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_employee.php");
    exit;
}

$id = (int) $_GET['id'];

// Escape function
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Fetch employee details
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    echo "Employee not found.";
    exit;
}

// Fetch 201 documents
$doc_stmt = $conn->prepare("SELECT * FROM documents WHERE employee_id = ?");
$doc_stmt->bind_param("i", $id);
$doc_stmt->execute();
$docs_result = $doc_stmt->get_result();

// Map of document type labels
$document_types = [
    'birth_cert' => 'Birth Certificate',
    'tin' => 'TIN',
    'sss' => 'SSS',
    'philhealth' => 'PhilHealth',
    'pagibig' => 'Pag-IBIG',
    'resume' => 'Resume',
    'id_picture' => 'ID Picture',
    'contract' => 'Employment Contract',
    'policy' => 'Signed Policies',
    'memo' => 'Memo',
    'incident_report' => 'Incident Report',
    'disciplinary_action' => 'Disciplinary Action',
    'commendation' => 'Commendation',
    'medical_clearance' => 'Medical Clearance',
    'exit_letter' => 'Exit Letter',
    'interview' => 'Exit Interview',
    'clearance' => 'Clearance Form'
];

// Required documents for "complete" status
$required_docs = ['sss', 'philhealth', 'pagibig', 'tin', 'birth_cert', 'resume', 'contract', 'policy'];

// Convert resultset to array once
$docs = [];
while ($doc = $docs_result->fetch_assoc()) {
    $docs[] = $doc;
}

// Determine existing and missing docs
$existing_docs = array_filter(array_map(fn($d) => $d['type'] ?? $d['document_type'] ?? '', $docs));
$existing_docs = array_unique($existing_docs);
$missing_docs = array_values(array_diff($required_docs, $existing_docs));

// Fetch evidence data BEFORE closing connection
$evidence_stmt = $conn->prepare("
    SELECT 
        id,
        evidence,
        evidence_file
    FROM incident_reports 
    WHERE employee_id = ?
");
$evidence_stmt->bind_param("i", $id);
$evidence_stmt->execute();
$evidence_result = $evidence_stmt->get_result();

$evidenceDataArray = [];
while ($evid = $evidence_result->fetch_assoc()) {
    $evidenceDataArray[(int)$evid['id']] = [
        'evidence' => $evid['evidence'] ?? '',
        'evidence_file' => $evid['evidence_file'] ?? ''
    ];
}
$evidence_stmt->close();

// Breadcrumb helper
function renderBreadcrumb($items = []) {
    $currentPage = basename($_SERVER['PHP_SELF'], ".php");
    $currentPageName = ucwords(str_replace('_', ' ', $currentPage));

    echo '<nav class="breadcrumb">';
    foreach ($items as $item) {
        echo '<a href="' . htmlspecialchars($item['link']) . '">' 
            . htmlspecialchars($item['label']) . '</a> › ';
    }
    echo '<span>' . htmlspecialchars($currentPageName) . '</span>';
    echo '</nav>';
}


// --- Role helpers ---
$role = $_SESSION['admin_role'] ?? '';
$isSuperAdmin = ($role === 'superadmin');
$isAdmin = ($role === 'admin');
$isStaff = ($role === 'staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Employee</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
    body { font-family: 'Segoe UI', sans-serif; background-color: #1e1e2d; margin: 0; }
    .container { padding: 20px; }
    .breadcrumb { font-size: 14px; margin-bottom: 20px; color: #070707; }
    .breadcrumb a { color: #007bff; text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }

    /* === Sidebar Collapse Fix === */
    .main-content {
        margin-left: 260px; /* expanded sidebar */
        padding: 20px;
        width: calc(100% - 260px);
        transition: margin-left 0.3s ease, width 0.3s ease;
        box-sizing: border-box;
    }

    /* When sidebar is collapsed */
    .sidebar.collapsed ~ .main-content {
        margin-left: 80px;
        width: calc(100% - 80px);
    }

    /* Ensure inner content stretches with it */
    .main-content .container {
        max-width: 100%;
        width: 100%;
    }

    .privacy-box {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        position: relative;
    }
    .privacy-box strong { color: #b45309; }
    .privacy-box button {
        position: absolute; top: 8px; right: 10px;
        background: none; border: none; color: #92400e;
        font-size: 16px; cursor: pointer;
    }

    /* Success/Error Messages */
    .alert {
        padding: 12px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .profile-header {
        display: flex; align-items: center;
        background: #3b3b4a; border-radius: 12px;
        padding: 20px; margin-bottom: 20px;
    }
    .profile-header img {
        width: 110px; height: 110px; border-radius: 50%;
        object-fit: cover; border: 3px solid #000; margin-right: 20px;
    }
    .profile-header .info h2 { margin: 0; font-size: 22px; color: #cbd5e1; }
    .profile-header .info p { margin: 5px 0; font-size: 14px; color: #cbd5e1; }

    .stats-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 15px; margin-bottom: 20px;
    }
    .stat-card {
        background: #3b3b4a; padding: 15px;
        border-radius: 12px; text-align: center;
    }
    .stat-card h3 { margin: 0; font-size: 18px; font-weight: 600; color: #cbd5e1; }
    .stat-card p { margin-top: 5px; font-size: 14px; color: #cbd5e1; }
    .green { border-top: 4px solid #28a745; }
    .blue { border-top: 4px solid #007bff; }
    .orange { border-top: 4px solid #fd7e14; }
    .red { border-top: 4px solid #dc3545; }

    .card {
        background: #3b3b4a; padding: 20px;
        border-radius: 12px; margin-bottom: 20px;
    }
    .card h3 { margin-top: 0; color: #007bff; }
    .card p { font-size: 14px; margin: 5px 0; color: #cbd5e1; }

    .action-buttons {
        display: flex; justify-content: center; gap: 15px;
        margin-bottom: 20px; flex-wrap: wrap;
    }
    .action-buttons a, .action-buttons button {
        padding: 10px 20px; border-radius: 20px;
        font-size: 14px; color: #fff; text-decoration: none;
        text-align: center; min-width: 150px; border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-primary { background-color: #007bff; }
    .btn-primary:hover { background-color: #0056b3; transform: translateY(-2px); }
    
    .btn-danger { background-color: #dc3545; }
    .btn-danger:hover { background-color: #c82333; transform: translateY(-2px); }

    table {
        width: 100%; border-collapse: collapse;
        margin-top: 15px; border-radius: 10px;
        overflow: hidden; background: #fff;
    }
    table thead { background: #f8f9fa; }
    table th, table td { padding: 12px 15px; font-size: 14px; text-align: left; }
    table tbody tr:nth-child(even) { background-color: #f1f3f5; }
    table tbody tr:hover { background-color: #e9ecef; }

    .btn-table {
        display: inline-block; padding: 6px 12px;
        border-radius: 16px; font-size: 12px;
        color: #fff; text-decoration: none;
        margin-right: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-view { background-color: #007bff; }
    .btn-view:hover { background-color: #0056b3; transform: translateY(-2px); }
    
    .btn-delete { background-color: #dc3545; }
    .btn-delete:hover { background-color: #c82333; transform: translateY(-2px); }
    
    .btn-edit { background-color: #f59e0b; }
    .btn-edit:hover { background-color: #d97706; transform: translateY(-2px); }
    
    .btn-evidence { background-color: #10b981; border: none; cursor: pointer; }
    .btn-evidence:hover { background-color: #059669; transform: translateY(-2px); }

    /* ===============================
       INCIDENT REPORT MODAL
    ================================= */
    .modal {
        display: none;
        position: fixed; 
        inset: 0;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(4px);
        z-index: 99999;
        justify-content: center;
        align-items: center;
    }
.modal-content {
    background: #081c49;
    width: 95vw;           /* ← wider */
    max-width: 1100px;     /* ← was 950px */
    height: 90vh;          /* ← taller */
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.45);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

#irIframe {
    flex: 1;
    width: 100%;
    min-width: 0;
    border: none;
    background: #ffffff;
}
    .close-btn {
        position: absolute;
        top: 12px;
        right: 18px;
        font-size: 26px;
        color: #cbd5e1;
        cursor: pointer;
        transition: 0.2s ease;
        z-index: 10;
    }

    .close-btn:hover {
        color: #ef4444;
    }

    #irIframe {
        flex: 1;
        width: 100%;
        border: none;
        background: #ffffff;
    }

    /* ===============================
       EVIDENCE MODAL
    ================================= */
    .evidence-modal {
        display: none;
        position: fixed; 
        inset: 0;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(4px);
        z-index: 99998;
        justify-content: center;
        align-items: center;
    }

    .evidence-modal-content {
        background: #1e293b;
        width: 90%;
        max-width: 800px;
        max-height: 85vh;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.45);
        position: relative;
        overflow: auto;
        padding: 20px;
    }

    .evidence-modal-close {
        position: absolute;
        top: 12px;
        right: 18px;
        font-size: 26px;
        color: #cbd5e1;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .evidence-modal-close:hover {
        color: #ef4444;
    }

    .evidence-container {
        margin-top: 30px;
    }

    .evidence-item {
        margin-bottom: 20px;
        padding: 15px;
        background: #0f172a;
        border-radius: 10px;
        border-left: 4px solid #10b981;
    }

    .evidence-label {
        font-size: 12px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .evidence-content {
        color: #cbd5e1;
        font-size: 14px;
        line-height: 1.6;
        word-break: break-word;
    }

    .evidence-file {
        margin-top: 10px;
    }

    .evidence-file a {
        display: inline-block;
        padding: 8px 16px;
        background: #10b981;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s ease;
    }

    .evidence-file a:hover {
        background: #059669;
        transform: translateY(-2px);
    }

    .evidence-image {
        max-width: 100%;
        max-height: 500px;
        border-radius: 8px;
        margin-top: 10px;
    }

    /* IR Status Badge */
    .ir-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
    }
    .ir-status.attendance { background: #dbeafe; color: #1e40af; }
    .ir-status.conduct { background: #fee2e2; color: #991b1b; }

    /* Creator Badge */
    .ir-creator {
        display: inline-block;
        padding: 3px 8px;
        background: #e0e7ff;
        color: #3730a3;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }

    /* Timestamp Info */
    .ir-timestamp {
        font-size: 12px;
        color: #666;
        margin-top: 8px;
    }

    /* Actions column responsive */
    .ir-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        white-space: normal;
    }

    /* ===============================
       DELETE CONFIRMATION MODAL
    ================================= */
    .delete-confirm-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 99997;
        justify-content: center;
        align-items: center;
    }

    .delete-confirm-content {
        background: linear-gradient(135deg, #1e3a8a 0%, #2d3e50 100%);
        width: 90%;
        max-width: 450px;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
        padding: 40px 30px;
        text-align: center;
        animation: slideInUp 0.3s ease;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .delete-icon {
        font-size: 60px;
        margin-bottom: 20px;
        display: inline-block;
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .delete-title {
        font-size: 24px;
        font-weight: 700;
        color: #f1f5f9;
        margin-bottom: 10px;
        margin-top: 0;
    }

    .delete-message {
        font-size: 14px;
        color: #cbd5e1;
        margin-bottom: 30px;
        line-height: 1.6;
    }

    .delete-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .delete-btn-cancel {
        padding: 12px 32px;
        border: 1.5px solid #475569;
        background: transparent;
        color: #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 130px;
    }

    .delete-btn-cancel:hover {
        background: #334155;
        border-color: #64748b;
        color: #fff;
    }

    .delete-btn-confirm {
        padding: 12px 32px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        min-width: 130px;
    }

    .delete-btn-confirm:hover {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.6);
        transform: translateY(-2px);
    }

    .delete-btn-confirm:active {
        transform: translateY(0);
    }
</style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container">
        <?php renderBreadcrumb([['label' => 'Manage Employee', 'link' => 'manage_employee.php']]); ?>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div class="alert alert-success">
                ✅ Incident report deleted successfully.
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] == 1): ?>
            <div class="alert alert-error">
                ❌ Error deleting incident report. Please try again.
            </div>
        <?php endif; ?>

        <!-- Privacy Notice -->
        <div class="privacy-box" id="privacyBox">
            <button onclick="document.getElementById('privacyBox').style.display='none'">×</button>
            <strong>Privacy Notice:</strong> This system contains personal and sensitive employee information protected under 
            <em>RA 10173 - Data Privacy Act of 2012</em>.
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <?php
            $id_picture = $employee['id_picture'] ?? '';
            $photo_path = !empty($id_picture) ? '../uploads/id_picture/' . basename($id_picture) : '../uploads/id_picture/default.png';

            // Build full name dynamically with middle initial
            $first_name = $employee['first_name'] ?? '';
            $middle_name = $employee['middle_name'] ?? '';
            $last_name = $employee['last_name'] ?? '';

            // Include middle initial if middle name exists
            $middle_initial = !empty($middle_name) ? strtoupper(substr(trim($middle_name), 0, 1)) . '.' : '';
            $full_name = trim($first_name . ' ' . $middle_initial . ' ' . $last_name);
            ?>
            <img src="<?= e($photo_path) ?>" alt="Profile Photo">
            <div class="info">
                <h2><?= e($full_name) ?></h2>
                <p><strong>Employee ID:</strong> <?= e($employee['employee_code'] ?? '') ?></p>
                <p><strong>Position:</strong> <?= e($employee['position'] ?? '') ?> - <?= e($employee['department'] ?? '') ?></p>
                <p><strong>Status:</strong> <?= e($employee['employment_status'] ?? '') ?></p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card green">
                <h3><?= e($employee['ss_number'] ?? '—') ?></h3>
                <p>SSS Number</p>
            </div>
            <div class="stat-card blue">
                <h3><?= e($employee['tin_number'] ?? '—') ?></h3>
                <p>TIN Number</p>
            </div>
            <div class="stat-card orange">
                <h3><?= e($employee['philhealth_number'] ?? '—') ?></h3>
                <p>PhilHealth Number</p>
            </div>
            <div class="stat-card red">
                <h3><?= e($employee['hmdf_number'] ?? '—') ?></h3>
                <p>HMDF Number</p>
            </div>
        </div>

        <!-- Personal Info -->
        <div class="card">
            <h3>Personal Information</h3>
            <p><strong>Full Name:</strong> <?= e(($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')) ?></p>
            <p><strong>Gender:</strong> <?= e($employee['gender'] ?? '') ?></p>
            <p><strong>Birthdate:</strong> <?= e($employee['birthdate'] ?? '') ?></p>
            <p><strong>Present Address:</strong> <?= e($employee['present_address'] ?? '') ?></p>
            <p><strong>Permanent Address:</strong> <?= e($employee['permanent_address'] ?? '') ?></p>
        </div>

        <!-- Contact Info -->
        <div class="card">
            <h3>Contact Information</h3>
            <p><strong>Email:</strong> <?= e($employee['email'] ?? '') ?></p>
            <p><strong>Phone:</strong> <?= e($employee['contact_no'] ?? '') ?></p>
            <p><strong>Emergency Contact:</strong> <?= e($employee['emergency_name'] ?? '') ?></p>
            <p><strong>Emergency Phone:</strong> <?= e($employee['emergency_no'] ?? '') ?></p>
        </div>

        <!-- Employment Info -->
        <div class="card">
            <h3>Employment Information</h3>
            <p><strong>Position:</strong> <?= e($employee['position'] ?? '') ?></p>
            <p><strong>Department:</strong> <?= e($employee['department'] ?? '') ?></p>
            <p><strong>Date Hired:</strong> <?= e($employee['date_hired'] ?? '') ?></p>
            <p><strong>Employment Status:</strong> <?= e($employee['employment_status'] ?? '') ?></p>

            <?php if ($employee['employment_status'] === 'resigned' && !empty($employee['date_resigned'])): ?>
                <p><strong>Date Resigned:</strong> <?= date("F d, Y", strtotime($employee['date_resigned'])) ?></p>
            <?php elseif ($employee['employment_status'] === 'retired' && !empty($employee['date_retired'])): ?>
                <p><strong>Date Retired:</strong> <?= date("F d, Y", strtotime($employee['date_retired'])) ?></p>
            <?php elseif ($employee['employment_status'] === 'fired' && !empty($employee['date_fired'])): ?>
                <p><strong>Date Fired:</strong> <?= date("F d, Y", strtotime($employee['date_fired'])) ?></p>
            <?php elseif ($employee['employment_status'] === 'active'): ?>
                <p><strong>Date End:</strong> N/A</p>
            <?php endif; ?>

            <?php if (!empty($employee['date_of_separation'])): ?>
                <p><strong>Date of Separation:</strong> <?= date("F d, Y", strtotime($employee['date_of_separation'])) ?></p>
            <?php else: ?>
                <p><strong>Date of Separation:</strong> N/A</p>
            <?php endif; ?>

            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin'): ?>
                <p><strong>Basic Salary:</strong> ₱ <?= number_format((float)$employee['basic_salary'], 2) ?></p>
            <?php endif; ?>
        </div>

        <!-- Employee Credentials -->
        <?php if ($isSuperAdmin): ?>
        <div class="card">
            <h3>Employee Login Credentials</h3>
            <p><strong>Username:</strong> <?= e($employee['username'] ?? '—') ?></p>
            <p>
                <strong>Password:</strong> 
                <span id="passwordField" style="letter-spacing:2px;">••••••••</span>
                <button type="button" onclick="togglePassword(this)" style="margin-left:10px;padding:4px 10px;border:none;border-radius:6px;background:#007bff;color:white;cursor:pointer;">Show</button>
            </p>
        </div>
        <?php endif; ?>

        <!-- Employee Documents -->
        <div class="card">
            <h3>Employee Documents</h3>
            <ul class="doc-checklist">
                <?php
                // Required documents: keys = database values, values = human labels
                $requiredDocs = [
                    "tin"         => "TIN",
                    "sss"         => "SSS",
                    "philhealth"  => "PhilHealth",
                    "pagibig"     => "Pag-IBIG",
                    "resume"      => "Resume",
                    "contract"    => "Employment Contract",
                    "policy"      => "Signed Policies",
                    "medical_clearance" => "Medical Clearance",
                    "birth_cert"  => "Birth Certificate"
                ];

                // Fetch uploaded docs for this employee
                $docsQuery = $conn->prepare("SELECT document_type FROM documents WHERE employee_id = ?");
                $docsQuery->bind_param("i", $id);
                $docsQuery->execute();
                $docsResult = $docsQuery->get_result();

                $uploadedDocs = [];
                while ($row = $docsResult->fetch_assoc()) {
                    if (!empty($row['document_type'])) {
                        $uploadedDocs[] = strtolower(trim($row['document_type']));
                    }
                }

                foreach ($requiredDocs as $type => $label): 
                    $hasDoc = in_array($type, $uploadedDocs);
                ?>
                    <li>
                        <label>
                            <input type="checkbox" disabled <?= $hasDoc ? "checked" : "" ?>>
                            <?= e($label) ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="edit_employee.php?id=<?= urlencode($employee['id']) ?>" class="btn-primary">Edit</a>

            <?php if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'staff'): ?>
                <a href="cert_of_emp.php?id=<?= urlencode($employee['id']) ?>" target="_blank" class="btn-primary">Generate Certificate</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin'): ?>
                <a href="cert_of_emp.php?id=<?= urlencode($employee['id']) ?>&type=COE_BASIC" target="_blank" class="btn-primary">Generate w/ Salary</a>
            <?php endif; ?>
            
            <a href="delete_employee.php?id=<?= urlencode($employee['id']) ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this employee?');">Delete</a>

            <!-- Generate IR Button -->
            <a href="incident_report.php?id=<?= urlencode($employee['id']) ?>" class="btn-primary">
                ➕ New Incident Report
            </a>
        </div>



        <!-- Evidence Modal -->
        <div id="evidenceModal" class="evidence-modal">
            <div class="evidence-modal-content">
                <span class="evidence-modal-close" onclick="closeEvidenceModal()">&times;</span>
                <h3 style="color: #cbd5e1; margin-top: 0;">Evidence & Documentation</h3>
                <div id="evidenceContent" class="evidence-container"></div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteConfirmModal" class="delete-confirm-modal">
            <div class="delete-confirm-content">
                <div class="delete-icon">⚠️</div>
                <h2 class="delete-title">Delete Incident Report?</h2>
                <p class="delete-message">
                    Are you sure you want to delete this incident report?<br>
                    This action cannot be undone. All evidence will be permanently removed.
                </p>
                <div class="delete-buttons">
                    <button class="delete-btn-cancel" onclick="cancelDelete()">Cancel</button>
                    <button class="delete-btn-confirm" onclick="confirmDelete()">Yes, Delete</button>
                </div>
            </div>
        </div>

        <!-- Documents -->
        <?php
        // ── FIX: Normalise file_path so it always includes the folder prefix ──
        function normaliseDocPath($raw_file_path) {
            if (empty($raw_file_path)) return '';
            // If it already contains a directory separator, assume it's already a full relative path
            if (strpos($raw_file_path, '/') !== false || strpos($raw_file_path, '\\') !== false) {
                return $raw_file_path;
            }
            // Otherwise it's a bare filename — prepend the documents folder
            return 'uploads/documents/' . $raw_file_path;
        }

        function renderDocsTable($docs, $title, $document_types, $employee) {
            if (count($docs) === 0) return;
            echo '<div class="card">';
            echo '<h3>' . e($title) . '</h3>';
            echo '<table><thead><tr><th>Type</th><th>Date Uploaded</th><th>Action</th></tr></thead><tbody>';
            foreach ($docs as $doc) {
                $doc_type_raw   = $doc['type'] ?? $doc['document_type'] ?? '';
                $doc_type_label = $document_types[$doc_type_raw] ?? e($doc_type_raw ?: 'Unknown');
                $raw_file_path  = $doc['file_path'] ?? '';

                // ── KEY FIX: build a correct relative URL from the admin subfolder ──
                // Admin pages live in /admin/, so '../' goes up one level to site root.
                // Documents are stored in /uploads/documents/
                $normalised     = normaliseDocPath($raw_file_path);          // e.g. uploads/documents/file.pdf
                $safe_file_url  = e('../' . ltrim($normalised, '/'));         // e.g. ../uploads/documents/file.pdf

                echo '<tr>';
                echo '<td>' . $doc_type_label . '</td>';
                echo '<td>' . e($doc['uploaded_at'] ?? '') . '</td>';
                echo '<td>';
                if (!empty($raw_file_path)) {
                    echo '<a class="btn-table btn-view" href="' . $safe_file_url . '" target="_blank">View</a>';
                }
                // Delete Button (block staff, allow admin/superadmin)
                if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'staff') {
                    echo '<a class="btn-table btn-delete" href="delete_document.php?id=' . urlencode($doc['id']) . '&employee_id=' . urlencode($employee['id']) . '" onclick="return confirm(\'Are you sure you want to delete this document?\');">Delete</a>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        $commendations = array_filter($docs, fn($d) =>
            ($d['type'] ?? $d['document_type']) === 'commendation'
        );
        $disciplinary = array_filter($docs, fn($d) =>
            in_array(($d['type'] ?? $d['document_type']), ['memo','incident_report','disciplinary_action'])
        );
        $exit_docs = array_filter($docs, fn($d) =>
            in_array(($d['type'] ?? $d['document_type']), ['clearance','exit_letter','interview'])
        );
        $others = array_filter($docs, fn($d) =>
            !in_array(($d['type'] ?? $d['document_type']), [
                'commendation','memo','incident_report','disciplinary_action',
                'clearance','exit_letter','interview'
            ])
        );

        echo '<div class="card"><h3>201 Documents</h3></div>';
        renderDocsTable($commendations, "Commendations", $document_types, $employee);

        // Staff cannot see Disciplinary Records
        if (!$isStaff) {
            renderDocsTable($disciplinary, "Disciplinary Records", $document_types, $employee);
        }

        renderDocsTable($exit_docs, "Exit Documents", $document_types, $employee);
        renderDocsTable($others, "Other Documents", $document_types, $employee);
        ?>

        <!-- INCIDENT REPORTS TABLE -->
        <div class="card">
            <h3>Incident Reports</h3>
            <?php
            // Fetch all IRs for this employee with creator info
            $ir_stmt = $conn->prepare("
                SELECT 
                    ir.id, 
                    ir.created_at, 
                    ir.nature, 
                    ir.attendance_detail, 
                    ir.conduct_detail, 
                    ir.offense_category, 
                    ir.action_taken, 
                    ir.evidence,
                    ir.evidence_file,
                    ir.pdf_file,
                    ir.created_by,
                    ir.updated_at,
                    ir.updated_by,
                    COALESCE(a.username, 'System') as creator_name,
                    COALESCE(u.username, '') as updater_name
                FROM incident_reports ir
                LEFT JOIN admins a ON ir.created_by = a.id
                LEFT JOIN admins u ON ir.updated_by = u.id
                WHERE ir.employee_id = ? 
                ORDER BY ir.created_at DESC
            ");
            
            if (!$ir_stmt) {
                echo "<p style='color: #e74c3c;'>Error: " . htmlspecialchars($conn->error) . "</p>";
            } else {
                $ir_stmt->bind_param("i", $id);
                $ir_stmt->execute();
                $ir_result = $ir_stmt->get_result();

                if ($ir_result->num_rows === 0) {
                    echo "<p style='color: #cbd5e1; margin: 10px 0;'>No incident reports found for this employee.</p>";
                } else {
                    echo '<table>
                        <thead>
                            <tr>
                                <th>Date Filed</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Created By</th>
                                <th>Updated</th>
                                <th>Evidence</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
                    while ($report = $ir_result->fetch_assoc()) {
                        $report_id = (int)$report['id'];
                        $nature = $report['nature'] ?? '';
                        $category = $report['offense_category'] ?? $report['attendance_detail'] ?? '';
                        $date_filed = date('M d, Y g:i A', strtotime($report['created_at']));
                        
                        // Creator info
                        $creator_name = $report['creator_name'] ?? 'System';
                        
                        // Updated info
                        $updated_info = '';
                        if (!empty($report['updated_at'])) {
                            $updated_date = date('M d, Y g:i A', strtotime($report['updated_at']));
                            $updater_name = $report['updater_name'] ?? 'System';
                            $updated_info = "$updated_date by $updater_name";
                        } else {
                            $updated_info = "Not updated";
                        }
                        
                        // Determine type badge
                        $type_label = ucfirst($nature);
                        $type_class = $nature === 'attendance' ? 'attendance' : 'conduct';
                        $category_label = ucfirst(str_replace('_', ' ', $category));
                        
                        // Check if evidence exists
                        $has_evidence = !empty($report['evidence']) || !empty($report['evidence_file']);
                        
                        echo "<tr>";
                        echo "<td><strong>" . e($date_filed) . "</strong></td>";
                        echo "<td><span class='ir-status " . $type_class . "'>" . e($type_label) . "</span></td>";
                        echo "<td>" . e($category_label) . "</td>";
                        echo "<td><span class='ir-creator'>" . e($creator_name) . "</span></td>";
                        echo "<td><small style='color: #666;'>" . e($updated_info) . "</small></td>";
                        echo "<td>";
                        if ($has_evidence) {
                            echo "<button class='btn-table btn-evidence' onclick='showEvidence(" . $report_id . ")'>📎 View</button>";
                        } else {
                            echo "<span style='color: #999; font-size: 12px;'>None</span>";
                        }
                        echo "</td>";
                        echo "<td class='ir-actions'>
                            <a class='btn-table btn-view' href='incident_report_pdf_viewer.php?id=" . urlencode($report_id) . "' target='_blank' title='View formatted PDF'>📄 View</a>
                            <a class='btn-table btn-edit' href='incident_report.php?id=" . urlencode($employee['id']) . "&report_id=" . urlencode($report_id) . "' target='_blank' title='Edit incident report'>✏️ Edit</a>";
                        
                        // Delete button for admins only
                        if (!$isStaff) {
                            echo "<button class='btn-table btn-delete' onclick=\"showDeleteConfirm('delete_incident_report.php?id=" . urlencode($report_id) . "&employee_id=" . urlencode($employee['id']) . "')\" title='Delete incident report'>🗑️ Delete</button>";
                        }
                        
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                }
                $ir_stmt->close();
            }
            ?>
        </div>
        <!-- END INCIDENT REPORTS TABLE -->

    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Evidence Data (passed from PHP) - CORRECTED
const evidenceData = <?= json_encode($evidenceDataArray) ?>;

let deleteUrl = '';

// Show delete confirmation modal
function showDeleteConfirm(url) {
    deleteUrl = url;
    document.getElementById('deleteConfirmModal').style.display = 'flex';
}

// Cancel delete
function cancelDelete() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
    deleteUrl = '';
}

// Confirm delete
function confirmDelete() {
    if (deleteUrl) {
        window.location.href = deleteUrl;
    }
}

// Close modal if clicking outside
document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        cancelDelete();
    }
});

// Show Evidence Modal
function showEvidence(reportId) {
    const data = evidenceData[reportId] || {};
    const content = document.getElementById('evidenceContent');
    let html = '';

    // Evidence text
    if (data.evidence) {
        html += `<div class="evidence-item">
            <div class="evidence-label">📝 Evidence Description</div>
            <div class="evidence-content">${escapeHtml(data.evidence)}</div>
        </div>`;
    }

    // Evidence file
    if (data.evidence_file) {
        const fileName = data.evidence_file.split('/').pop();
        const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(fileName);
        
        html += `<div class="evidence-item">
            <div class="evidence-label">📎 Attached File</div>
            <div class="evidence-file">
                <a href="../${escapeHtml(data.evidence_file)}" target="_blank" download>⬇️ Download: ${escapeHtml(fileName)}</a>
            </div>`;
        
        if (isImage) {
            html += `<img src="../${escapeHtml(data.evidence_file)}" alt="Evidence" class="evidence-image">`;
        }
        
        html += `</div>`;
    }

    if (!html) {
        html = '<div class="evidence-item"><p style="color: #999;">No evidence documentation found.</p></div>';
    }

    content.innerHTML = html;
    document.getElementById('evidenceModal').style.display = 'flex';
}

// Close Evidence Modal
function closeEvidenceModal() {
    document.getElementById('evidenceModal').style.display = 'none';
}

// HTML Escape Helper
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Close modal if clicking outside
document.getElementById('evidenceModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEvidenceModal();
    }
});

// Generate IR - handled by direct link now

// Password Toggle
function togglePassword(btn) {
    const field = document.getElementById('passwordField');
    const plain = <?= isset($employee['plain_password']) ? json_encode($employee['plain_password']) : '""' ?>;
    if (field.textContent.includes('•')) {
        field.textContent = plain;
        btn.textContent = "Hide";
    } else {
        field.textContent = "••••••••";
        btn.textContent = "Show";
    }
}
</script>

<?php
$doc_stmt->close();
$conn->close();
?>

</body>
</html>
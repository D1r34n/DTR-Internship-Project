<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once(__DIR__ . '/../auth/session_check.php');
include '../includes/header.php';
include '../includes/sidebar.php';
require_once '../includes/breadcrumb.php';
require_once __DIR__ . '/../includes/config.php';

$role = $_SESSION['admin_role'] ?? '';
$isSuperAdmin = ($role === 'superadmin');
$isStaff = ($role === 'staff');

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<div id='main-content'><h2>Invalid Employee ID.</h2></div>";
    include '../includes/footer.php';
    exit;
}

// Escape helper
function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Fetch employee info
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    echo "<div id='main-content'><h2>Employee not found.</h2></div>";
    include '../includes/footer.php';
    exit;
}

// ✅ Fetch existing documents
$doc_stmt = $conn->prepare("SELECT document_type, file_path FROM documents WHERE employee_id = ?");
$doc_stmt->bind_param("i", $id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();
$documents = [];
while ($row = $doc_result->fetch_assoc()) {
    $documents[$row['document_type']] = $row['file_path'];
}

// List of possible document types
$document_types = [
    'birth_cert' => 'Birth Certificate',
    'tin' => 'TIN',
    'sss' => 'SSS',
    'philhealth' => 'PhilHealth',
    'pagibig' => 'Pag-IBIG',
    'resume' => 'Resume',
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
?>

<style>
body {
    background-color: #1e1e2d;
    color: #fff;
    font-family: 'Segoe UI', sans-serif;
}

#main-content {
    margin-left: 210px;
    padding: 30px;
    flex: 1;
    max-width: 100%;
    transition: margin-left 0.3s ease, width 0.3s ease;
    width: calc(100% - 260px);
}

.sidebar.collapsed ~ #main-content {
    margin-left: 50px;
    width: calc(100% - 80px);
}

#main-content form {
    max-width: 100%;
    width: 100%;
}
.header-container {
    height: 70px;
}
        h2 {
            position: absolute;
            top: 80px;
        }
h3 { 
    color: #fff; 
    font-weight: 600; 
    margin-bottom: 20px; }

form {
    background-color: #2d2d3a;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from {opacity: 0; transform: translateY(-20px);}
    to {opacity: 1; transform: translateY(0);}
}

label { font-weight: 500; display: block; margin-top: 15px; color: #ccc; }

input[type="text"],
input[type="date"],
textarea,
input[type="file"],
select,
input[type="number"] {
    width: 100%;
    padding: 10px 12px;
    margin-top: 5px;
    border: 1px solid #444;
    border-radius: 8px;
    font-size: 14px;
    background-color: #1e1e2d;
    color: #fff;
}

input[type="submit"] {
    margin-top: 30px;
    background: linear-gradient(90deg, #6a5acd, #4cafef);
    color: #fff;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: 0.3s;
}
input[type="submit"]:hover { opacity: 0.9; transform: translateY(-2px); }

hr { border: none; border-top: 1px solid #444; margin: 30px 0; }

#add-document-btn {
    margin-bottom: 15px;
    background: linear-gradient(90deg, #6a5acd, #4cafef);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
}

.document-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    padding: 12px;
    border-radius: 8px;
    background: #252536;
    border: 1px solid #333;
}
.document-row .filename { flex: 1; color: #aaa; font-size: 13px; font-style: italic; }
.document-row button {
    background: #e53e3e;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.2s;
}
.document-row button:hover { background: #c53030; }

.doc-list { margin: 15px 0; padding: 12px; background: #252536; border-radius: 8px; }
.doc-list ul { list-style: none; padding-left: 0; }
.doc-list li { padding: 6px 0; border-bottom: 1px solid #333; color: #ccc; font-size: 14px; }
.doc-list li:last-child { border-bottom: none; }
.doc-list a { color: #4cafef; text-decoration: none; }
.doc-list a:hover { text-decoration: underline; }
</style>

<div id="main-content">
    <?php
    renderBreadcrumb([
        ['label' => 'Manage Employee', 'link' => 'manage_employee.php'],
        ['label'=> 'View Employee', 'link'=> 'view_employee.php?id=' . urlencode($id)]
    ]);
    ?>
   <div class="header-container">
    <h2><i class="fas fa-user-edit"></i> Edit Employee</h2>
   </div>
   <div class="content-container">
  
    <form action="update_employee.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= h($employee['id']) ?>">

        <!-- Basic Info -->
        <label>Employee Code:</label>
        <input type="text" name="employee_code" value="<?= h($employee['employee_code']) ?>" required>

        <label>First Name:</label>
        <input type="text" name="first_name" value="<?= h($employee['first_name']) ?>" required>

        <label>Middle Name:</label>
        <input type="text" name="middle_name" value="<?= h($employee['middle_name']) ?>">
        
        <label>Last Name:</label>
        <input type="text" name="last_name" value="<?= h($employee['last_name']) ?>" required>

        <label>Gender:</label>
        <select name="gender" required>
            <option value="Male" <?= ($employee['gender'] ?? '')==='Male'?'selected':'' ?>>Male</option>
            <option value="Female" <?= ($employee['gender'] ?? '')==='Female'?'selected':'' ?>>Female</option>
  /*        <option value="Non-binary" <?= ($employee['gender'] ?? '')==='Non-binary'?'selected':'' ?>>Non-binary</option>*/
        </select>

        <label>Birthdate:</label>
        <input type="date" name="birthdate" value="<?= h($employee['birthdate']) ?>">

        <label>Contact No:</label>
        <input type="text" name="contact_no" value="<?= h($employee['contact_no']) ?>" required>

        <label>Present Address:</label>
        <textarea name="present_address" rows="2"><?= h($employee['present_address']) ?></textarea>

        <label>Permanent Address:</label>
        <textarea name="permanent_address" rows="2"><?= h($employee['permanent_address']) ?></textarea>

        <label>Emergency Contact Name:</label>
        <input type="text" name="emergency_name" value="<?= h($employee['emergency_name']) ?>">

        <label>Emergency Contact No:</label>
        <input type="text" name="emergency_no" value="<?= h($employee['emergency_no']) ?>">

        <label>Position:</label>
        <input type="text" name="position" value="<?= h($employee['position']) ?>" required>

        <label>Department:</label>
        <select name="department" required>
            <?php
            $departments = ["Supply Chain","Sales","MIS","Logistics","Marketing","HR","Finance","ESG","CSD",
                            "Contact Center - Acer US","Contact Center - AOCC","Contact Center - APHI,MY,SG,SocMed","BRC"];
            foreach($departments as $dept){
                $sel = ($employee['department'] ?? '') === $dept ? 'selected' : '';
                echo "<option value=\"".h($dept)."\" $sel>".h($dept)."</option>";
            }
            ?>
        </select>

        <label>SSS Number:</label>
        <input type="text" name="ss_number" value="<?= h($employee['ss_number']) ?>" required>

        <label>TIN Number:</label>
        <input type="text" name="tin_number" value="<?= h($employee['tin_number']) ?>" required>

        <label>PhilHealth Number:</label>
        <input type="text" name="philhealth_number" value="<?= h($employee['philhealth_number']) ?>" required>

        <label>HMDF Number:</label>
        <input type="text" name="hmdf_number" value="<?= h($employee['hmdf_number']) ?>" required>

        <label>Employee Status:</label>
        <select name="employment_status" id="employment_status" required>
            <option value="active" <?= ($employee['employment_status'] ?? '')==='active'?'selected':'' ?>>Active</option>
            <option value="resigned" <?= ($employee['employment_status'] ?? '')==='resigned'?'selected':'' ?>>Resigned</option>
            <option value="retired" <?= ($employee['employment_status'] ?? '')==='retired'?'selected':'' ?>>Retired</option>
            <option value="fired" <?= ($employee['employment_status'] ?? '')==='fired'?'selected':'' ?>>Fired</option>
        </select>

<div id="date_resigned_container" style="display:none;">
    <label>Date Resigned:</label>
    <input type="date" name="date_resigned" value="<?= h($employee['date_resigned']) ?>">
</div>
<div id="date_retired_container" style="display:none;">
    <label>Date Retired:</label>
    <input type="date" name="date_retired" value="<?= h($employee['date_retired']) ?>">
</div>
<div id="date_fired_container" style="display:none;">
    <label>Date Fired:</label>
    <input type="date" name="date_fired" value="<?= h($employee['date_fired']) ?>">
</div>

<!-- Only ONE date_of_separation -->
<div id="date_of_separation_container" style="display:none;">
    <label>Date of Separation:</label>
    <input type="date" name="date_of_separation" value="<?= h($employee['date_of_separation']) ?>">
</div>


        <?php if($isSuperAdmin): ?>
        <label>Basic Salary:</label>
        <input type="number" name="basic_salary" step="0.01" min="0" value="<?= h($employee['basic_salary']) ?>">
        <?php endif; ?>

        <hr>
        <h3>Existing Documents</h3>
        <?php if(!empty($documents)): ?>
            <div class="doc-list">
                <ul>
                <?php foreach($documents as $type => $file_path): ?>
                    <?php
                    // Staff should NOT see disciplinary documents
                    if ($isStaff && in_array($type, ['memo','incident_report','disciplinary_action'])) {
                        continue;
                    }
                    ?>
                    <li>
                        <?= h($document_types[$type] ?? ucfirst($type)) ?>:
                        <a href="../uploads/<?= h($file_path) ?>" target="_blank"><?= h(basename($file_path)) ?></a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <p style="color:#aaa;">No documents uploaded yet.</p>
        <?php endif; ?>

        <hr>
        <h3>Upload New Documents</h3>
        <button type="button" id="add-document-btn">+ Add Document</button>
        <div id="dynamic-documents-container"></div>

        <input type="submit" value="Update Employee">
    </form>
        </div>
        </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("dynamic-documents-container");
    const addBtn = document.getElementById("add-document-btn");

    const documentTypes = {
        'birth_cert':'Birth Certificate','tin':'TIN','sss':'SSS','philhealth':'PhilHealth',
        'pagibig':'Pag-IBIG','resume':'Resume','contract':'Employment Contract','policy':'Signed Policies','medical_clearance':'Medical Clearance',
        'memo':'Memo','incident_report':'Incident Report','disciplinary_action':'Disciplinary Action','commendation':'Commendation',
        'exit_letter':'Exit Letter','interview':'Exit Interview','clearance':'Clearance Form'
    };

    function addDocumentRow() {
        const row = document.createElement("div");
        row.classList.add("document-row");

        const select = document.createElement("select");
        select.name = "document_type[]";
        select.required = true;
        select.appendChild(new Option("-- Select Document Type --", ""));
        for (const key in documentTypes) {
            select.appendChild(new Option(documentTypes[key], key));
        }

        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.name = "document_file[]";
        fileInput.required = true;

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.textContent = "Remove";
        removeBtn.addEventListener("click", () => row.remove());

        row.append(select, fileInput, removeBtn);
        container.appendChild(row);
    }

    addBtn.addEventListener("click", addDocumentRow);

    const statusSelect = document.getElementById("employment_status");
    const resignedContainer = document.getElementById("date_resigned_container");
    const retiredContainer = document.getElementById("date_retired_container");
    const firedContainer = document.getElementById("date_fired_container");

function toggleDateFields() {
    resignedContainer.style.display = (statusSelect.value === "resigned") ? "block" : "none";
    retiredContainer.style.display = (statusSelect.value === "retired") ? "block" : "none";
    firedContainer.style.display = (statusSelect.value === "fired") ? "block" : "none";

    // Always show date_of_separation if resigned/retired/fired
    document.getElementById("date_of_separation_container").style.display =
        (["resigned","retired","fired"].includes(statusSelect.value)) ? "block" : "none";
}

    toggleDateFields();
    statusSelect.addEventListener("change", toggleDateFields);
});
</script>

<?php include '../includes/footer.php'; ?>

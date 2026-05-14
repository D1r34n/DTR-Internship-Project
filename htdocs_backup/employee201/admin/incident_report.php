<?php
session_start();
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/db.php';
require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid employee.");
}

$employee_id = (int) $_GET['id'];

// ── Check if we're editing an existing report ──────────────────────────
$report_id   = isset($_GET['report_id']) && is_numeric($_GET['report_id'])
               ? (int) $_GET['report_id']
               : null;
$incident    = null;   // will hold existing report data if editing

if ($report_id) {
    $r_stmt = $conn->prepare("
        SELECT * FROM incident_reports
        WHERE id = ? AND employee_id = ?
    ");
    $r_stmt->bind_param("ii", $report_id, $employee_id);
    $r_stmt->execute();
    $r_result = $r_stmt->get_result();
    $incident = $r_result->fetch_assoc();
    $r_stmt->close();

    if (!$incident) {
        die("Incident report not found.");
    }
}

// ── Employee info ──────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT employee_code, first_name, middle_name, last_name, position, department
    FROM employees
    WHERE id = ?
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result   = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found.");
}

// ── Logged-in admin / preparer info ───────────────────────────────────
$admin_id       = $_SESSION['admin_id'] ?? null;
$admin_name     = 'MANAGEMENT';
$admin_position = 'Human Resources Department';

if ($admin_id) {
    $admin_stmt = $conn->prepare("
        SELECT first_name, middle_name, last_name, position
        FROM employees WHERE id = ?
    ");
    $admin_stmt->bind_param("i", $admin_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();

    if ($admin_result->num_rows > 0) {
        $admin_data = $admin_result->fetch_assoc();
        $fname      = trim($admin_data['first_name']  ?? '');
        $mname      = trim($admin_data['middle_name'] ?? '');
        $lname      = trim($admin_data['last_name']   ?? '');
        $admin_name = $mname ? "$fname $mname $lname" : "$fname $lname";
        $admin_name = trim($admin_name);
        $admin_position = trim($admin_data['position'] ?? 'Human Resources Department');
    }
    $admin_stmt->close();
}

// ── Helper: safe output ────────────────────────────────────────────────
function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// ── Pre-populate helpers ───────────────────────────────────────────────
$pre_datetime = '';
if (!empty($incident['incident_datetime'])) {
    $pre_datetime = date('Y-m-d\TH:i', strtotime($incident['incident_datetime']));
}

$pre_nature            = $incident['nature']            ?? '';
$pre_attendance_detail = $incident['attendance_detail'] ?? '';
$pre_offense_category  = $incident['offense_category']  ?? '';
$pre_conduct_detail    = $incident['conduct_detail']    ?? '';
$pre_evidence          = $incident['evidence']          ?? '';
$pre_action_taken      = $incident['action_taken']      ?? '';
$pre_suspension_days   = $incident['suspension_days']   ?? 1;
$pre_recommendations   = $incident['recommendations']   ?? '';

$pre_dates_json = '';
if (!empty($incident['minutes_data'])) {
    $pre_dates_json = $incident['minutes_data'];
}

$is_editing = ($report_id !== null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $is_editing ? 'Edit Incident Report' : 'Incident Report' ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="incident_report.css">
    <style>
        table td { background: #f0f0f1; color: #cbd5e1; }

        .edit-banner {
            background: linear-gradient(90deg,#f59e0b,#d97706);
            color:#fff;
            padding:10px 20px;
            border-radius:8px;
            font-weight:600;
            font-size:14px;
            margin-bottom:18px;
            display:flex;
            align-items:center;
            gap:10px;
        }
        @media (max-width: 700px) {
    .employee-grid {
        grid-template-columns: 1fr !important;
    }
    .incident-form {
        padding: 10px !important;
    }
}

.employee-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
    </style>
</head>
<body>
<div class="main-content" style="
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; width:100%; min-height:100vh;
    margin:0 auto; padding:20px; box-sizing:border-box;
    position:relative; left:0;
">
    <div style="width:100%;max-width:680px;margin-bottom:8px;">
        <a href="view_employee.php?id=<?= (int)$employee_id ?>"
           style="display:inline-flex;align-items:center;gap:6px;
                  background:#10b981;color:#fff;text-decoration:none;
                  padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;
                  border:none;transition:background .2s,transform .15s;box-shadow:0 2px 8px rgba(16,185,129,.3);"
           onmouseover="this.style.background='#059669';this.style.transform='translateY(-2px)';"
           onmouseout="this.style.background='#10b981';this.style.transform='translateY(0)';">
            ← Back to Employee
        </a>
    </div>

    <h1 style="text-align:center;width:100%;">
        <?= $is_editing ? '✏️ Edit Incident Report' : 'Incident Report Form' ?>
    </h1>

    <?php if ($is_editing): ?>
    <div class="edit-banner" style="width:100%;max-width:680px;">
        ✏️ You are editing an existing report.
        Changes will overwrite the saved record.
    </div>
    <?php endif; ?>

    <form class="incident-form" id="incidentForm"
          method="POST" action="incident_report_submit.php"
          style="width:100%;max-width:680px;margin:0 auto;display:block;">

        <!-- Hidden fields -->
        <input type="hidden" name="employee_id"   value="<?= (int)$employee_id ?>">
        <input type="hidden" name="report_id"     value="<?= $report_id ?? '' ?>">
        <input type="hidden" id="adminName"        value="<?= h($admin_name) ?>">
        <input type="hidden" id="adminPosition"    value="<?= h($admin_position) ?>">
        <input type="hidden" id="employeeCode"      value="<?= h($employee['employee_code'] ?? '') ?>">
        <input type="hidden" id="employeeName"      value="<?= h(trim(($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))) ?>">
        <input type="hidden" id="employeePosition"  value="<?= h($employee['position'] ?? '') ?>">
        <input type="hidden" id="employeeDepartment" value="<?= h($employee['department'] ?? '') ?>">

        <!-- ── Employee Details ── -->
        <h3 class="section-title">Employee Details</h3>
        <div class="employee-grid">
            <div class="info-box">
                <label>Employee Code</label>
                <div class="info-value"><?= h($employee['employee_code'] ?? '') ?></div>
            </div>
            <div class="info-box">
                <label>Employee Name</label>
                <div class="info-value"><?= h(trim(
                    ($employee['first_name']   ?? '') . ' ' .
                    ($employee['middle_name']  ?? '') . ' ' .
                    ($employee['last_name']    ?? '')
                )) ?></div>
            </div>
            <div class="info-box">
                <label>Position</label>
                <div class="info-value"><?= h($employee['position']   ?? '') ?></div>
            </div>
            <div class="info-box">
                <label>Department</label>
                <div class="info-value"><?= h($employee['department'] ?? '') ?></div>
            </div>
        </div>

        <!-- ── Date & Time ── -->
        <h3>Date &amp; Time of Incident</h3>
        <label for="incident_datetime">Date &amp; Time of Incident</label>
        <input type="datetime-local" id="incident_datetime"
               name="incident_datetime" required
               value="<?= h($pre_datetime) ?>">

        <!-- ── Nature ── -->
        <h3>Nature of Incident</h3>
        <select id="incidentCategory" name="nature" required>
            <option value="">-- Select Category --</option>
            <option value="attendance" <?= $pre_nature === 'attendance' ? 'selected' : '' ?>>Attendance and Punctuality</option>
            <option value="conduct"    <?= $pre_nature === 'conduct'    ? 'selected' : '' ?>>Conduct and Behavior</option>
        </select>

        <!-- ATTENDANCE OPTIONS -->
        <div id="attendanceOptions" style="display:none;">
            <label>Attendance Issue</label>
            <select id="attendanceType" name="attendance_detail">
                <option value="">-- Select Issue --</option>
                <option value="late"             <?= $pre_attendance_detail === 'late'             ? 'selected' : '' ?>>Repeated late arrivals</option>
                <option value="awol"             <?= $pre_attendance_detail === 'awol'             ? 'selected' : '' ?>>Absent without notice (AWOL)</option>
                <option value="not_logging"      <?= $pre_attendance_detail === 'not_logging'      ? 'selected' : '' ?>>Not logging attendance properly</option>
            </select>
        </div>

        <!-- ATTENDANCE: Offense Category (shown after attendance issue) -->
        <div id="attendanceOffenseCategory" style="display:none;margin-top:14px;">
            <label>Offense Category</label>
            <select id="offenseCategory" name="offense_category" onchange="updateConductIssues()">
                <option value="">-- Select Offense Category --</option>
                <option value="minor" <?= $pre_offense_category === 'minor' ? 'selected' : '' ?>>Minor Offense</option>
                <option value="major" <?= $pre_offense_category === 'major' ? 'selected' : '' ?>>Major Offense</option>
            </select>
        </div>

        <!-- ATTENDANCE DETAILS (dates/minutes) -->
        <div id="attendanceDetails" style="display:none;">
            <div id="minutesBox" style="display:none;">
                <h3 id="minutesTitle">Minutes of Late</h3>
                <input type="number" id="singleMinutesField" name="minutes_value" min="0" placeholder="Enter total minutes">
            </div>

            <h3 id="datesTitle">Dates of Late in a Month</h3>
            <div class="dates-container">
                <div class="date-input-group">
                    <input type="date" class="date-input" id="datePickerInput" placeholder="Select a date">
                    <input type="number" class="minutes-input" id="minutesInputField"
                           placeholder="Minutes late" min="0" style="display:none;">
                    <button type="button" class="btn-add-date" aria-label="Add date">+</button>
                </div>
                <div id="datesListContainer" class="dates-list-container"></div>
                <input type="hidden" id="datesField"   name="dates_in_month">
                <input type="hidden" id="minutesData"  name="minutes_data">
            </div>
        </div>

        <!-- CONDUCT OPTIONS: Conduct Issue first, then Offense Category -->
        <div id="conductOptions" style="display:none;">
            <div id="conductOffenseCategory" style="display:none;margin-top:14px;">
                <label>Offense Category</label>
                <select id="conductOffenseCategorySelect" name="offense_category" onchange="updateConductIssues()">
                    <option value="">-- Select Offense Category --</option>
                    <option value="minor" <?= $pre_offense_category === 'minor' ? 'selected' : '' ?>>Minor Offense</option>
                    <option value="major" <?= $pre_offense_category === 'major' ? 'selected' : '' ?>>Major Offense</option>
                </select>
            </div>
            <div id="conductIssueBox" style="display:none;margin-top:12px;">
                <label>Conduct Issue</label>
                <select id="conductDetailSelect" name="conduct_detail">
                    <option value="">-- Select Issue --</option>
                </select>
            </div>
        </div>

        <!-- ── Evidence ── -->
        <h3>Evidence/Documentation</h3>
        <textarea name="evidence" rows="3"
                  placeholder="Description: Timesheets, witness statements, emails, etc."><?= h($pre_evidence) ?></textarea>

        <label style="margin-top:10px;display:block;font-size:13px;color:#94a3b8;">
            Attach File (optional)
            <?php if (!empty($incident['evidence_file'])): ?>
                <span style="color:#10b981;font-size:12px;">
                    – current: <?= h(basename($incident['evidence_file'])) ?>
                </span>
            <?php endif; ?>
        </label>
        <input type="file" name="evidence_file" id="evidenceFile"
               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
               style="margin-top:6px;padding:8px;background:#1e293b;
                      border:1px dashed #475569;border-radius:6px;
                      color:#cbd5e1;font-size:13px;width:100%;cursor:pointer;">
        <!-- FIX: removed inline display:flex so display:none works correctly -->
        <div id="filePreviewBox" style="display:none;margin-top:8px;padding:10px 14px;
             background:#0f172a;border-radius:6px;border:1px solid #334155;
             font-size:12px;color:#94a3b8;align-items:center;gap:10px;">
            <span id="filePreviewIcon" style="font-size:20px;">📎</span>
            <div>
                <div id="filePreviewName" style="color:#e2e8f0;font-weight:600;"></div>
                <div id="filePreviewSize" style="font-size:11px;margin-top:2px;"></div>
            </div>
            <button type="button" onclick="clearFile()"
                    style="margin-left:auto;background:#ef4444;border:none;
                           border-radius:4px;color:white;padding:3px 8px;
                           cursor:pointer;font-size:11px;">✕ Remove</button>
        </div>

        <!-- ── Action Taken ── -->
        <h3>Immediate Action Taken</h3>
        <select name="action_taken" id="actionTakenSelect" onchange="updateActionTakenOther()">
            <option value="">-- Select Action Taken --</option>
            <?php
            $actions = [
                'Verbal Warning','Written Warning','Counseling',
                'Suspension','Demotion','Termination','Other'
            ];
            foreach ($actions as $a):
                $sel = ($pre_action_taken === $a) ? 'selected' : '';
            ?>
            <option value="<?= h($a) ?>" <?= $sel ?>><?= h($a) ?></option>
            <?php endforeach; ?>
        </select>

        <div id="suspensionDaysBox" style="display:none;margin-top:10px;background:#1e293b;
             border:1px solid #334155;border-radius:8px;padding:12px 16px;">
            <label style="font-size:13px;color:#94a3b8;display:block;margin-bottom:6px;">
                📅 Number of Suspension Days
            </label>
            <div style="display:flex;align-items:center;gap:10px;">
                <input type="number" id="suspensionDays" name="suspension_days"
                       min="1" max="30" value="<?= (int)$pre_suspension_days ?>"
                       style="width:90px;padding:8px 10px;background:#0f172a;
                              border:1px solid #475569;border-radius:6px;
                              color:#f1f5f9;font-size:15px;font-weight:700;text-align:center;">
                <span style="color:#94a3b8;font-size:13px;">day(s)</span>
                <span id="suspensionDaysLabel"
                      style="color:#f59e0b;font-size:12px;font-weight:600;margin-left:4px;"></span>
            </div>
        </div>

        <textarea id="actionTakenOther" name="action_taken_other" rows="2"
                  placeholder="Please specify the action taken..."
                  style="display:none;margin-top:8px;"></textarea>

        <!-- ── Recommendations ── -->
        <h3>Recommendations/Follow-up</h3>
        <textarea name="recommendations" rows="3"
                  placeholder="Steps to prevent recurrence, monitoring plan"><?= h($pre_recommendations) ?></textarea>

        <button type="submit" class="btn-save">
            <?= $is_editing ? 'Update Incident Report' : 'Submit Incident Report' ?>
        </button>
    </form>

    <!-- ── PDF Preview Section ── -->
    <div id="pdfPreviewSection" class="pdf-preview-section">
        <div class="preview-header">
            <div class="preview-title">📄 PDF Preview</div>
        </div>
        <div class="preview-content">
            <div class="preview-field">
                <div class="preview-label">Employee Code</div>
                <div class="preview-value"><?= h($employee['employee_code'] ?? '') ?></div>
            </div>
            <div class="preview-field">
                <div class="preview-label">Employee Name</div>
                <div class="preview-value"><?= h(trim(
                    ($employee['first_name']  ?? '') . ' ' .
                    ($employee['middle_name'] ?? '') . ' ' .
                    ($employee['last_name']   ?? '')
                )) ?></div>
            </div>
            <div class="preview-field">
                <div class="preview-label">Position</div>
                <div class="preview-value"><?= h($employee['position']   ?? '') ?></div>
            </div>
            <div class="preview-field">
                <div class="preview-label">Department</div>
                <div class="preview-value"><?= h($employee['department'] ?? '') ?></div>
            </div>
            <div class="preview-field">
                <div class="preview-label">Date &amp; Time of Incident</div>
                <div class="preview-value" id="previewDateTime">-</div>
            </div>
            <div class="preview-field">
                <div class="preview-label">Nature of Incident</div>
                <div class="preview-value" id="previewNature">-</div>
            </div>
            <div class="preview-field" id="previewAttendanceFieldContainer" style="display:none;">
                <div class="preview-label">Attendance Issue</div>
                <div class="preview-value" id="previewAttendance">-</div>
            </div>
            <div class="preview-field" id="previewDatesFieldContainer" style="display:none;">
                <div class="preview-label">Dates</div>
                <div class="preview-value" id="previewDatesDisplay">
                    <div class="preview-dates-display" id="previewDatesList"></div>
                </div>
            </div>
            <div class="preview-field" id="previewOffenseCatContainer" style="display:none;">
                <div class="preview-label">Offense Category</div>
                <div class="preview-value" id="previewOffenseCat">-</div>
            </div>
            <div class="preview-field" id="previewConductFieldContainer" style="display:none;">
                <div class="preview-label">Conduct Issue</div>
                <div class="preview-value" id="previewConduct">-</div>
            </div>
            <div class="preview-field preview-full-width">
                <div class="preview-label">Evidence/Documentation</div>
                <div class="preview-value preview-textarea" id="previewEvidence">-</div>
            </div>
            <div class="preview-field" id="previewFileContainer" style="display:none;">
                <div class="preview-label">Attached File</div>
                <div class="preview-value" id="previewFile">-</div>
            </div>
            <div class="preview-field preview-full-width">
                <div class="preview-label">Immediate Action Taken</div>
                <div class="preview-value preview-textarea" id="previewAction">-</div>
            </div>
            <div class="preview-field preview-full-width">
                <div class="preview-label">Recommendations/Follow-up</div>
                <div class="preview-value preview-textarea" id="previewRecommendations">-</div>
            </div>
        </div>
    </div>

    <!-- PDF Modal with Editable Content -->
    <div id="pdfPreviewModal" class="pdf-modal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <div class="pdf-modal-title-section">
                    <h2>✏️ Incident Report Editor</h2>
                </div>
                <div class="pdf-modal-actions">
                    <button type="button" class="btn-modal-action btn-print-letter"
                            onclick="printPDFPreview()" title="Print">🖨️ Print</button>
                    <button type="button" class="pdf-modal-close"
                            onclick="closePDFModal()">×</button>
                </div>
            </div>
            <div class="edit-mode-warning" id="editModeWarning">
                <span class="edit-hint-badge">✏️ Click anywhere in the document to edit freely</span>
                <span style="opacity:.6;font-size:11px;">
                    Bold: <kbd style="background:#334155;border:1px solid #475569;border-radius:3px;padding:1px 5px;color:#e2e8f0;">Ctrl+B</kbd>
                    &nbsp; Italic: <kbd style="background:#334155;border:1px solid #475569;border-radius:3px;padding:1px 5px;color:#e2e8f0;">Ctrl+I</kbd>
                    &nbsp; Auto-saves as you type
                </span>
                <span class="changes-counter" id="changesCounter">0 edits</span>
                <button class="undo-btn" id="undoBtn" onclick="undoLastEdit()" disabled>↩ Undo</button>
            </div>
            <div class="pdf-modal-body" id="pdfModalBody">
                <!-- PDF Letter will be rendered here -->
            </div>
        </div>
    </div>
</div>

<script>
// ── PHP → JS bridge for edit mode ─────────────────────────────────────
const IS_EDITING    = <?= $is_editing ? 'true' : 'false' ?>;
const REPORT_ID     = <?= $report_id ?? 'null' ?>;

const PRE_DATES_JSON = <?= json_encode(!empty($pre_dates_json) ? (json_decode($pre_dates_json, true) ?? []) : []) ?>;

const PRE_NATURE           = <?= json_encode($pre_nature) ?>;
const PRE_ATTENDANCE_DETAIL= <?= json_encode($pre_attendance_detail) ?>;
const PRE_OFFENSE_CATEGORY = <?= json_encode($pre_offense_category) ?>;
const PRE_CONDUCT_DETAIL   = <?= json_encode($pre_conduct_detail) ?>;
const PRE_ACTION_TAKEN     = <?= json_encode($pre_action_taken) ?>;

// ── Editor state ───────────────────────────────────────────────────────
let editedContent = {
    date:'', employeeName:'', employeePosition:'', employeeDepartment:'',
    natureLabel:'', attendanceLabel:'', evidence:'', actionTaken:'',
    recommendations:'', para1:'', para2:'', para3:'', para4:'',
    para5:'', para6:'', para7:'', greeting:'', signatureTitle:'', signatureName:''
};

let docHistory    = [];
let autoSaveTimer = null;
let totalEdits    = 0;

function recordDocSnapshot() {
    const editor = document.getElementById('letterDocEditor');
    if (!editor) return;
    docHistory.push(editor.innerHTML);
    if (docHistory.length > 50) docHistory.shift();
    totalEdits++;
    updateUndoUI();
    showAutoSaveBadge();
}

function undoLastEdit() {
    if (docHistory.length <= 1) return;
    docHistory.pop();
    const previous = docHistory[docHistory.length - 1];
    const editor   = document.getElementById('letterDocEditor');
    if (editor) { editor.innerHTML = previous; syncEditedContentFromEditor(); }
    totalEdits = Math.max(0, totalEdits - 1);
    updateUndoUI();
}

function updateUndoUI() {
    const undoBtn = document.getElementById('undoBtn');
    const counter = document.getElementById('changesCounter');
    if (undoBtn)  undoBtn.disabled = docHistory.length <= 1;
    if (counter) {
        counter.textContent = totalEdits + (totalEdits === 1 ? ' edit' : ' edits');
        counter.classList.toggle('visible', totalEdits > 0);
    }
}

function showAutoSaveBadge() {
    const badge = document.getElementById('autosaveBadge');
    if (!badge) return;
    badge.classList.add('saved');
    badge.querySelector('.toolbar-autosave-dot').style.background = '#34d399';
    badge.querySelector('span').textContent = 'Saved';
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(() => {
        badge.classList.remove('saved');
        badge.querySelector('.toolbar-autosave-dot').style.background = '#94a3b8';
        badge.querySelector('span').textContent = 'Auto-save on';
    }, 2000);
}

function syncEditedContentFromEditor() {
    const editor = document.getElementById('letterDocEditor');
    if (!editor) return;
    editor.querySelectorAll('[data-field]').forEach(el => {
        const key = el.dataset.field;
        if (key) editedContent[key] = el.innerText.trim();
    });
}

document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'z') {
        const modal = document.getElementById('pdfPreviewModal');
        if (modal && modal.style.display === 'flex') {
            e.preventDefault();
            undoLastEdit();
        }
    }
});

// ── Attendance config ──────────────────────────────────────────────────
const attendanceLabels = {
    late:              { minutesTitle:'Minutes of Late', showMinutes:true,  datesTitle:'Dates of Late in a Month' },
    awol:              { minutesTitle:'',               showMinutes:false, datesTitle:'Dates of AWOL in a Month' },
    frequent_absences: { minutesTitle:'',               showMinutes:false, datesTitle:'Dates of Absence in a Month' },
    early_leave:       { minutesTitle:'',               showMinutes:false, datesTitle:'Dates of Early Leave in a Month' },
    not_logging:       { minutesTitle:'',               showMinutes:false, datesTitle:'Dates Not Logged in a Month' }
};

let selectedDatesData = [];

// ── Helper: get the active offense category select ─────────────────────
// Returns the value from whichever offense category select is currently visible
function getOffenseCategoryValue() {
    const nature = document.getElementById('incidentCategory').value;
    if (nature === 'conduct') {
        return document.getElementById('conductOffenseCategorySelect').value;
    }
    return document.getElementById('offenseCategory').value;
}

// ── Section toggling ───────────────────────────────────────────────────
function toggleCategorySections() {
    const category = document.getElementById('incidentCategory').value;

    document.getElementById('attendanceOptions').style.display =
        category === 'attendance' ? 'block' : 'none';
    document.getElementById('attendanceOffenseCategory').style.display =
        category === 'attendance' ? 'block' : 'none';
    document.getElementById('conductOptions').style.display =
        category === 'conduct' ? 'block' : 'none';

    // Show conduct offense category inside conductOptions when conduct is selected
    if (category === 'conduct') {
        document.getElementById('conductOffenseCategory').style.display = 'block';
    }

    if (category !== 'attendance') {
        document.getElementById('attendanceDetails').style.display = 'none';
        document.getElementById('singleMinutesField').value = '';
        selectedDatesData = [];
        document.getElementById('datePickerInput').value   = '';
        document.getElementById('minutesInputField').value = '';
        document.getElementById('datesField').value  = '';
        document.getElementById('minutesData').value = '';
        updateDatesList();
    }

    if (category !== 'conduct') {
        document.getElementById('conductDetailSelect').innerHTML =
            '<option value="">-- Select Issue --</option>';
        document.getElementById('conductIssueBox').style.display = 'none';
        document.getElementById('conductOffenseCategory').style.display = 'none';
    }

    updatePreviewAll();
}

function setDatePickerMaxDate() {
    const today = new Date();
    const max   = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
    const dp    = document.getElementById('datePickerInput');
    if (dp) dp.max = max;
}

function updateAttendanceDetails() {
    const category = document.getElementById('incidentCategory').value;
    const type     = document.getElementById('attendanceType').value;

    if (category !== 'attendance' || !type) {
        document.getElementById('attendanceDetails').style.display = 'none';
        return;
    }

    const config = attendanceLabels[type];
    if (!config) { document.getElementById('attendanceDetails').style.display = 'none'; return; }

    const minutesBox        = document.getElementById('minutesBox');
    const minutesInputField = document.getElementById('minutesInputField');

    if (config.showMinutes) {
        minutesBox.style.display        = 'block';
        minutesInputField.style.display = 'block';
    } else {
        minutesBox.style.display        = 'none';
        minutesInputField.style.display = 'none';
        document.getElementById('singleMinutesField').value = '';
    }

    document.getElementById('datesTitle').textContent    = config.datesTitle;
    document.getElementById('attendanceDetails').style.display = 'block';
    setDatePickerMaxDate();
}

// ── Date management ────────────────────────────────────────────────────
function addDate() {
    const dateInput    = document.getElementById('datePickerInput');
    const minutesInput = document.getElementById('minutesInputField');
    const dateValue    = dateInput.value;
    const minutesValue = minutesInput.value;
    const attendanceType = document.getElementById('attendanceType').value;
    const config         = attendanceLabels[attendanceType];

    if (!dateValue) { alert('Please select a date.'); return; }

    const selected = new Date(dateValue + 'T00:00:00');
    const today    = new Date(); today.setHours(0,0,0,0);
    if (selected > today) { alert('Future dates are not allowed.'); return; }

    if (config.showMinutes && (!minutesValue || minutesValue <= 0)) {
        alert('Please enter minutes late.'); return;
    }
    if (selectedDatesData.some(d => d.date === dateValue)) {
        alert('This date has already been added.'); return;
    }

    selectedDatesData.push({ date:dateValue, minutes:config.showMinutes ? parseInt(minutesValue) : 0 });
    selectedDatesData.sort((a,b) => new Date(a.date) - new Date(b.date));
    dateInput.value   = '';
    minutesInput.value= '';
    updateDatesList();
    updatePreviewDates();
}

function removeDate(dateValue) {
    selectedDatesData = selectedDatesData.filter(d => d.date !== dateValue);
    updateDatesList();
    updatePreviewDates();
}

function updateDatesList() {
    const container   = document.getElementById('datesListContainer');
    const hiddenField = document.getElementById('datesField');
    const minutesField= document.getElementById('minutesData');
    container.innerHTML = '';

    if (!selectedDatesData.length) { hiddenField.value = ''; minutesField.value = ''; return; }

    const attendanceType = document.getElementById('attendanceType').value;
    const config         = attendanceLabels[attendanceType] || {};

    selectedDatesData.forEach(item => {
        const dateObj     = new Date(item.date + 'T00:00:00');
        const displayDate = dateObj.toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'});
        const div         = document.createElement('div');
        div.className     = 'date-item';
        div.innerHTML     = config.showMinutes
            ? `<div class="date-item-details">
                   <span class="date-item-value">${displayDate}</span>
                   <span class="date-item-minutes">${item.minutes} min</span>
               </div>
               <button type="button" class="date-item-remove" onclick="removeDate('${item.date}')">×</button>`
            : `<div class="date-item-details">
                   <span class="date-item-value">${displayDate}</span>
               </div>
               <button type="button" class="date-item-remove" onclick="removeDate('${item.date}')">×</button>`;
        container.appendChild(div);
    });

    hiddenField.value  = selectedDatesData.map(d => new Date(d.date+'T00:00:00').getDate()).join(', ');
    minutesField.value = JSON.stringify(selectedDatesData);
}

function updatePreviewDates() {
    const attendanceType = document.getElementById('attendanceType').value;
    const config         = attendanceLabels[attendanceType] || {};

    if (selectedDatesData.length > 0) {
        document.getElementById('previewDatesFieldContainer').style.display = 'flex';
        const list = document.getElementById('previewDatesList');
        list.innerHTML = '';
        selectedDatesData.forEach(item => {
            const dateObj = new Date(item.date + 'T00:00:00');
            const display = dateObj.toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'});
            const badge   = document.createElement('span');
            badge.className   = 'preview-date-badge';
            badge.textContent = config.showMinutes ? `${display} - ${item.minutes} min` : display;
            list.appendChild(badge);
        });
    } else {
        document.getElementById('previewDatesFieldContainer').style.display = 'none';
    }
}

// ── Preview panel ──────────────────────────────────────────────────────
function updatePreviewAll() {
    const dateTime = document.getElementById('incident_datetime').value;
    document.getElementById('previewDateTime').textContent = dateTime
        ? new Date(dateTime).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})
        : '-';

    const nature = document.getElementById('incidentCategory').value;
    document.getElementById('previewNature').textContent =
        { attendance:'Attendance and Punctuality', conduct:'Conduct and Behavior' }[nature] || '-';

    const attendanceType  = document.getElementById('attendanceType').value;
    const attendanceLabel = {
        late:'Repeated late arrivals', awol:'Absent without notice (AWOL)',
        frequent_absences:'Frequent or unexplained absences',
        early_leave:'Leaving work early without permission',
        not_logging:'Not logging attendance properly'
    }[attendanceType] || '-';

    if (nature === 'attendance' && attendanceType) {
        document.getElementById('previewAttendanceFieldContainer').style.display = 'flex';
        document.getElementById('previewAttendance').textContent = attendanceLabel;
    } else {
        document.getElementById('previewAttendanceFieldContainer').style.display = 'none';
    }

    const offenseCat = getOffenseCategoryValue();
    if ((nature === 'attendance' || nature === 'conduct') && offenseCat) {
        document.getElementById('previewOffenseCatContainer').style.display = 'flex';
        document.getElementById('previewOffenseCat').textContent =
            offenseCat === 'minor' ? 'Minor Offense' : 'Major Offense';
    } else {
        document.getElementById('previewOffenseCatContainer').style.display = 'none';
    }

    const conduct = document.getElementById('conductDetailSelect').value;
    if (nature === 'conduct' && conduct) {
        document.getElementById('previewConductFieldContainer').style.display = 'flex';
        document.getElementById('previewConduct').textContent = conduct;
    } else {
        document.getElementById('previewConductFieldContainer').style.display = 'none';
    }

    const evidence = document.querySelector('textarea[name="evidence"]').value || '-';
    document.getElementById('previewEvidence').textContent  = evidence;
    document.getElementById('previewEvidence').className    =
        `preview-value preview-textarea${evidence === '-' ? ' empty' : ''}`;

    const actionSel  = document.getElementById('actionTakenSelect').value;
    const actionOther= document.getElementById('actionTakenOther').value;
    const suspDays   = document.getElementById('suspensionDays').value;
    let action = '-';
    if (actionSel === 'Other')      action = actionOther || 'Other';
    else if (actionSel === 'Suspension') action = `Suspension – ${suspDays} day(s)`;
    else action = actionSel || '-';
    document.getElementById('previewAction').textContent = action;
    document.getElementById('previewAction').className   =
        `preview-value preview-textarea${action === '-' ? ' empty' : ''}`;

    const reco = document.querySelector('textarea[name="recommendations"]').value || '-';
    document.getElementById('previewRecommendations').textContent = reco;
    document.getElementById('previewRecommendations').className   =
        `preview-value preview-textarea${reco === '-' ? ' empty' : ''}`;
}

// ── Document Editor (letter modal) ────────────────────────────────────
function setupDocumentEditor() {
    const editor = document.getElementById('letterDocEditor');
    if (!editor) return;
    if (!docHistory.length) docHistory.push(editor.innerHTML);

    let inputDebounce = null;
    editor.addEventListener('input', function() {
        clearTimeout(inputDebounce);
        inputDebounce = setTimeout(() => { recordDocSnapshot(); syncEditedContentFromEditor(); }, 600);
    });
    editor.addEventListener('blur', function() {
        clearTimeout(inputDebounce);
        recordDocSnapshot();
        syncEditedContentFromEditor();
    }, true);

    document.querySelectorAll('.toolbar-btn').forEach(btn => {
        btn.addEventListener('mousedown', function(e) {
            e.preventDefault();
            document.execCommand(this.dataset.cmd, false, this.dataset.val || null);
            editor.focus();
        });
    });
}

function setupEditableFields() { setupDocumentEditor(); }
function makeFieldEditable()   {}
function updateLetterPreviewWithEdits() {
    const editor = document.getElementById('letterDocEditor');
    if (editor) syncEditedContentFromEditor();
}

function viewPDFPreview() {
    const modal    = document.getElementById('pdfPreviewModal');
    const modalBody= document.getElementById('pdfModalBody');
    const warning  = document.getElementById('editModeWarning');

    if (Object.values(editedContent).every(v => v === '')) {
        docHistory  = [];
        totalEdits  = 0;
        updateUndoUI();
    }

    modalBody.innerHTML = `
        <div class="letter-editor-wrap">
            <div id="letterDocEditor"
                 class="letter-doc-editable letter-document"
                 contenteditable="true" spellcheck="true"
                 style="padding:50px;background:white;min-height:500px;outline:none;">
                ${generateLetterHTML()}
            </div>
        </div>`;

    warning.classList.add('active');
    modal.style.display = 'flex';
    setupDocumentEditor();
}

function closePDFModal() {
    document.getElementById('pdfPreviewModal').style.display = 'none';
    document.getElementById('editModeWarning').classList.remove('active');
}

function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, m =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

function generateLetterHTML() {
    const adminName     = document.getElementById('adminName').value;
    const adminPosition = document.getElementById('adminPosition').value;

    const employee = {
        code:       '<?= h($employee['employee_code'] ?? '') ?>',
        name:       '<?= h(trim(($employee['first_name']??'').' '.($employee['middle_name']??'').' '.($employee['last_name']??''))) ?>',
        position:   '<?= h($employee['position']   ?? '') ?>',
        department: '<?= h($employee['department'] ?? '') ?>'
    };

    const dateTime   = document.getElementById('incident_datetime').value;
    const incidentDate = dateTime
        ? new Date(dateTime).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})
        : 'N/A';

    const nature        = document.getElementById('incidentCategory').value;
    const natureLabel   = nature === 'attendance' ? 'Attendance and Punctuality' : 'Conduct and Behavior';
    const attendanceType= document.getElementById('attendanceType').value;
    const attendanceLabel = {
        late:'Repeated late arrivals', awol:'Absent without notice (AWOL)',
        frequent_absences:'Frequent or unexplained absences',
        early_leave:'Leaving work early without permission',
        not_logging:'Not logging attendance properly'
    }[attendanceType] || '';

    const offenseCat      = getOffenseCategoryValue();
    const offenseCatLabel = offenseCat === 'minor' ? 'Minor Offense' : offenseCat === 'major' ? 'Major Offense' : '';
    const conduct         = document.getElementById('conductDetailSelect').value;

    const evidence    = editedContent.evidence   !== '' ? editedContent.evidence   : document.querySelector('textarea[name="evidence"]').value;
    const actionSel   = document.getElementById('actionTakenSelect').value;
    const actionOther = document.getElementById('actionTakenOther').value;
    const suspDays    = document.getElementById('suspensionDays').value;
    let actionTakenRaw= actionSel==='Other' ? actionOther : (actionSel==='Suspension' ? `Suspension – ${suspDays} day(s)` : actionSel);
    const actionTaken = editedContent.actionTaken       !== '' ? editedContent.actionTaken       : actionTakenRaw;
    const recommendations= editedContent.recommendations!== '' ? editedContent.recommendations : document.querySelector('textarea[name="recommendations"]').value;

    const today           = editedContent.date           || new Date().toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
    const employeeName    = editedContent.employeeName    || employee.name;
    const employeePosition= editedContent.employeePosition|| employee.position;
    const employeeDepartment=editedContent.employeeDepartment||employee.department;
    const signatureName   = editedContent.signatureName   || adminName;
    const signatureTitle  = editedContent.signatureTitle  || adminPosition;
    const natureDisplayLabel=editedContent.natureLabel    || natureLabel;
    const greetingName    = editedContent.greeting        || employeeName.split(' ')[0];

    let tardinessTable = '';
    if (selectedDatesData.length > 0 && nature === 'attendance' && attendanceType === 'late') {
        tardinessTable = `<p>The following tardiness records have been documented:</p>
            <table class="letter-table"><thead><tr>
                <th>Date</th><th style="text-align:center;">Minutes Late</th>
            </tr></thead><tbody>
            ${selectedDatesData.map(item=>`<tr>
                <td>${new Date(item.date+'T00:00:00').toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})}</td>
                <td style="text-align:center;">${item.minutes} minutes</td>
            </tr>`).join('')}
            </tbody></table>`;
    } else if (selectedDatesData.length > 0 && nature === 'attendance' && attendanceType !== 'late') {
        const datesList = selectedDatesData.map(item=>
            new Date(item.date+'T00:00:00').toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})).join(', ');
        tardinessTable = `<p>Dates recorded: ${datesList}</p>`;
    }

    const para1 = editedContent.para1 || (nature==='attendance'
        ? `This letter serves as a formal Incident Report concerning your attendance record on ${incidentDate}.`
        : `This letter serves as a formal Incident Report regarding a conduct matter on ${incidentDate}.`);
    const para2 = editedContent.para2 || (nature==='attendance'
        ? `Based on the attendance log, the following attendance issue has been documented: <strong>${escapeHtml(attendanceLabel)}</strong>${offenseCatLabel?` (${escapeHtml(offenseCatLabel)})`:''}.`
        : `The following <strong>${escapeHtml(offenseCatLabel)}</strong> conduct issue has been documented: <strong>${escapeHtml(conduct)}</strong>.`);
    const para3 = editedContent.para3 || (nature==='attendance'
        ? `It is critical that you understand that habitual tardiness negatively affects team operations, productivity, and overall workplace efficiency. While we understand that unforeseen circumstances may occur, employees are expected to notify their immediate supervisor in advance whenever possible.`
        : `This conduct must be corrected immediately. Continued violations may result in further disciplinary action up to and including termination of employment.`);
    const para4 = editedContent.para4 || (nature==='attendance'
        ? `You are hereby requested to submit a written explanation within twenty-four (24) hours upon receipt of this notice. Further violations of company attendance policies may lead to more serious disciplinary action in accordance with company rules and regulations.`
        : `You are required to meet with the Human Resources Department within forty-eight (48) hours to discuss this matter.`);
    const para5 = editedContent.para5 || (nature==='attendance'
        ? `We trust that you will take the necessary corrective measures to improve your attendance and adhere strictly to company policies moving forward.`
        : '');
    const para6 = editedContent.para6 || 'Should you wish to discuss this matter, you may coordinate with the Human Resources Department.';
    const para7 = editedContent.para7 || 'Thank you for your prompt attention to this matter.';

    return `<div class="letter-document" style="font-family:'Calibri','Georgia',serif;font-size:13px;line-height:1.8;color:#333;">
        <div class="letter-date" style="margin-bottom:30px;">
            <p>Date: <span data-field="date">${escapeHtml(today)}</span></p>
        </div>
        <div class="letter-recipient" style="margin-bottom:30px;line-height:1.8;">
            <div data-field="employeeName"    style="font-weight:600;margin-bottom:3px;">${escapeHtml(employeeName)}</div>
            <div data-field="employeePosition">${escapeHtml(employeePosition)}</div>
            <div data-field="employeeDepartment">${escapeHtml(employeeDepartment)}</div>
           
        </div>
        <div class="letter-subject" style="margin:30px 0;">
            <p><strong>Subject: Formal Incident Report – <span data-field="natureLabel">${escapeHtml(natureDisplayLabel)}</span></strong></p>
        </div>
        <div class="letter-greeting" style="margin-bottom:20px;">
            <p>Dear <span data-field="greeting">${escapeHtml(greetingName)}</span>,</p>
        </div>
        <div class="letter-body" style="text-align:justify;margin-bottom:25px;">
            <p data-field="para1">${para1}</p>
            <p data-field="para2">${para2}</p>
            ${tardinessTable}
            ${evidence?`<p data-field="evidence"><strong>Evidence/Details:</strong> ${escapeHtml(evidence)}</p>`:''}
            <p data-field="para3">${para3}</p>
            <p data-field="para4">${para4}</p>
            ${actionTaken?`<p data-field="actionTaken"><strong>Immediate Action Taken:</strong> ${escapeHtml(actionTaken)}</p>`:''}
            ${recommendations?`<p data-field="recommendations"><strong>Recommendations/Follow-up:</strong> ${escapeHtml(recommendations)}</p>`:''}
            ${para5?`<p data-field="para5">${para5}</p>`:''}
            <p data-field="para6">${para6}</p>
            <p data-field="para7">${para7}</p>
        </div>
        <div class="letter-closing" style="margin-top:40px;">
            <p>Sincerely,</p>
            <div class="letter-signature" style="display:inline-block;margin-top:20px;">
                <div class="letter-signature-line" style="width:200px;border-top:1px solid #000;margin-bottom:8px;"></div>
                <div data-field="signatureTitle" style="font-size:13px;color:#333;margin:3px 0;">${escapeHtml(signatureTitle)}</div>
                <div data-field="signatureName"  style="font-size:13px;color:#333;margin:3px 0;">${escapeHtml(signatureName)}</div>
            </div>
        </div>
    </div>`;
}

// ── Conduct issues ─────────────────────────────────────────────────────
const offenseIssues = {
    minor:[
        'Improper uniform or dress code violation',
        'Unauthorized use of mobile phone during work hours',
        'Failure to follow standard operating procedures',
        'Minor discourtesy to colleagues','Littering or failure to maintain cleanliness'
    ],
    major:[
        'Job abandonment',
        'Insubordination or refusal to follow instructions',
        'Dishonesty or falsification of records',
        'Theft or misappropriation of company property',
        'Physical assault or fighting in the workplace',
        'Sexual harassment or misconduct',
        'Gross negligence causing damage or loss',
        'Unauthorized disclosure of confidential information',
        'Violation of company policies',
        'Conflict with colleagues or supervisors',
        'Misuse of company resources or property'
    ]
};

function updateConductIssues() {
    const nature = document.getElementById('incidentCategory').value;
    if (nature !== 'conduct') { updatePreviewAll(); return; }

    const cat = document.getElementById('conductOffenseCategorySelect').value;
    const box = document.getElementById('conductIssueBox');
    const sel = document.getElementById('conductDetailSelect');

    if (!cat) { box.style.display = 'none'; updatePreviewAll(); return; }

    sel.innerHTML = '<option value="">-- Select Issue --</option>';
    offenseIssues[cat].forEach(issue => {
        const opt = document.createElement('option');
        opt.value = opt.textContent = issue;
        if (IS_EDITING && issue === PRE_CONDUCT_DETAIL) opt.selected = true;
        sel.appendChild(opt);
    });
    box.style.display = 'block';
    updatePreviewAll();
}

// ── Action taken helpers ───────────────────────────────────────────────
function updateActionTakenOther() {
    const val = document.getElementById('actionTakenSelect').value;
    document.getElementById('actionTakenOther').style.display    = val === 'Other'      ? 'block' : 'none';
    document.getElementById('suspensionDaysBox').style.display   = val === 'Suspension' ? 'block' : 'none';
    updateSuspensionLabel();
    updatePreviewAll();
}

function updateSuspensionLabel() {
    const days = parseInt(document.getElementById('suspensionDays').value) || 1;
    document.getElementById('suspensionDaysLabel').textContent =
        days === 1 ? '(1 day suspension)' : `(${days} days suspension)`;
}

// ── File preview ───────────────────────────────────────────────────────
document.getElementById('evidenceFile').addEventListener('change', function() {
    const file = this.files[0];
    const box  = document.getElementById('filePreviewBox');
    if (!file) { box.style.display = 'none'; return; }
    const icons = {pdf:'📄',jpg:'🖼️',jpeg:'🖼️',png:'🖼️',doc:'📝',docx:'📝',xls:'📊',xlsx:'📊'};
    const ext   = file.name.split('.').pop().toLowerCase();
    document.getElementById('filePreviewIcon').textContent = icons[ext] || '📎';
    document.getElementById('filePreviewName').textContent = file.name;
    document.getElementById('filePreviewSize').textContent = (file.size/1024).toFixed(1)+' KB';
    box.style.display = 'flex';
    document.getElementById('previewFileContainer').style.display = 'flex';
    document.getElementById('previewFile').textContent = file.name;
});

function clearFile() {
    document.getElementById('evidenceFile').value = '';
    document.getElementById('filePreviewBox').style.display      = 'none';
    document.getElementById('previewFileContainer').style.display= 'none';
    document.getElementById('previewFile').textContent           = '-';
}

// ── Form submit (AJAX) ─────────────────────────────────────────────────
document.getElementById('incidentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    Object.keys(editedContent).forEach(key => formData.append(`edited_${key}`, editedContent[key]));

    fetch('incident_report_submit.php', {
        method:'POST', body:formData,
        headers:{'X-Requested-With':'XMLHttpRequest'}
    })
    .then(res => res.text())
    .then(data => {
        let res;
        try { res = JSON.parse(data); } catch(e) { res = {ok:false,message:data}; }
        if (!res.ok) { alert('Error: ' + (res.message || 'Unknown error')); return; }

        if (res.pdf_url) {
            window.location.href = res.pdf_url;
        } else {
            alert(IS_EDITING ? 'Incident Report Updated Successfully' : 'Incident Report Submitted Successfully');
        }
    })
    .catch(error => { console.error('Error:', error); alert('Error: ' + error.message); });
});

// ── Event listeners ────────────────────────────────────────────────────
document.getElementById('incidentCategory').addEventListener('change', function() {
    document.getElementById('attendanceType').value = '';
    document.getElementById('singleMinutesField').value = '';
    document.getElementById('datePickerInput').value    = '';
    document.getElementById('minutesInputField').value  = '';
    document.getElementById('datesListContainer').innerHTML = '';
    document.getElementById('datesField').value   = '';
    document.getElementById('minutesData').value  = '';
    document.querySelector('select[name="conduct_detail"]').value = '';
    toggleCategorySections();
    updateAttendanceDetails();
    updatePreviewAll();
});

document.getElementById('attendanceType').addEventListener('change', function() {
    selectedDatesData = [];
    document.getElementById('datePickerInput').value   = '';
    document.getElementById('minutesInputField').value = '';
    document.getElementById('datesField').value  = '';
    document.getElementById('minutesData').value = '';
    updateDatesList();
    updateAttendanceDetails();
    updatePreviewAll();
});

document.getElementById('incident_datetime').addEventListener('change', updatePreviewAll);
document.querySelector('textarea[name="evidence"]').addEventListener('input', updatePreviewAll);
document.querySelector('textarea[name="recommendations"]').addEventListener('input', updatePreviewAll);
document.querySelector('.btn-add-date').addEventListener('click', e => { e.preventDefault(); addDate(); });
document.getElementById('datePickerInput').addEventListener('keypress', e => { if(e.key==='Enter'){e.preventDefault();addDate();} });
document.getElementById('minutesInputField').addEventListener('keypress', e => { if(e.key==='Enter'){e.preventDefault();addDate();} });
document.getElementById('conductDetailSelect').addEventListener('change', updatePreviewAll);
document.getElementById('conductOffenseCategorySelect').addEventListener('change', updatePreviewAll);
document.getElementById('offenseCategory').addEventListener('change', updatePreviewAll);
document.getElementById('actionTakenSelect').addEventListener('change', updatePreviewAll);

// ── DOMContentLoaded – init + restore edit values ──────────────────────
document.addEventListener('DOMContentLoaded', function() {

    document.getElementById('suspensionDays').addEventListener('input', function() {
        updateSuspensionLabel();
        updatePreviewAll();
    });

    toggleCategorySections();

    if (IS_EDITING) {
        if (PRE_NATURE === 'attendance' && PRE_ATTENDANCE_DETAIL) {
            // Set the dropdown VALUE first, THEN call updateAttendanceDetails
            // so it reads the correct type and doesn't clear selectedDatesData
            document.getElementById('attendanceType').value = PRE_ATTENDANCE_DETAIL;
            updateAttendanceDetails();

            // Restore saved dates AFTER the section is shown
            if (Array.isArray(PRE_DATES_JSON) && PRE_DATES_JSON.length > 0) {
                selectedDatesData = PRE_DATES_JSON;
                updateDatesList();
                updatePreviewDates();
            }
        }

        if (PRE_NATURE === 'conduct' && PRE_OFFENSE_CATEGORY) {
            document.getElementById('conductOffenseCategorySelect').value = PRE_OFFENSE_CATEGORY;
            updateConductIssues();
        }

        updateActionTakenOther();
    }

    updateSuspensionLabel();
    updatePreviewAll();
});
</script>

</body>
</html>
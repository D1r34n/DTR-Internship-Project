<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/employee201/includes/auth.php';
include $_SERVER['DOCUMENT_ROOT'] . '/employee201/includes/db.php';
require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid incident report.");
}

$report_id = (int) $_GET['id'];

$stmt = $conn->prepare("
    SELECT ir.*, e.employee_code, e.first_name, e.middle_name, e.last_name,
           e.position, e.department, e.email, e.contact_no
    FROM incident_reports ir
    JOIN employees e ON ir.employee_id = e.id
    WHERE ir.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();
$stmt->close();

if (!$report) die("Incident report not found.");

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function getOrdinalSuffix($number) {
    $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) return $number . 'th';
    return $number . $ends[$number % 10];
}

$fullName      = trim(($report['first_name'] ?? '') . ' ' . ($report['middle_name'] ?? '') . ' ' . ($report['last_name'] ?? ''));
$incidentDate  = $report['incident_datetime'] ? date('F d, Y', strtotime($report['incident_datetime'])) : 'N/A';
$incidentTime  = $report['incident_datetime'] ? date('g:i A',  strtotime($report['incident_datetime'])) : 'N/A';
$submittedDate = $report['created_at']        ? date('F d, Y', strtotime($report['created_at']))        : date('F d, Y');

$attendanceDetail = $report['attendance_detail'] ?? '';

// Fetch date records
$datesData = [];
$t_stmt = $conn->prepare("
    SELECT tardiness_date AS date, minutes_late AS minutes
    FROM incident_report_tardiness
    WHERE incident_report_id = ?
    ORDER BY tardiness_date ASC
");
if ($t_stmt) {
    $t_stmt->bind_param("i", $report_id);
    $t_stmt->execute();
    $t_res = $t_stmt->get_result();
    while ($row = $t_res->fetch_assoc()) $datesData[] = $row;
    $t_stmt->close();
}
if (empty($datesData) && !empty($report['minutes_data'])) {
    $decoded = json_decode($report['minutes_data'], true);
    if (is_array($decoded)) $datesData = $decoded;
}

function buildTardinessTable($data) {
    if (empty($data)) return '';
    $rows = '';
    foreach ($data as $r) {
        $d = date('F d, Y', strtotime($r['date']));
        $m = (int)($r['minutes'] ?? 0);
        $rows .= "<tr><td>{$d}</td><td style='text-align:center;'>{$m} min</td></tr>";
    }
    return "<p style='margin:10px 0 5px;font-size:13px;'>The following tardiness records have been documented:</p>
        <table class='att-table'><thead><tr><th>Date</th><th>Minutes Late</th></tr></thead><tbody>{$rows}</tbody></table>";
}
function buildAwolTable($data) {
    if (empty($data)) return '';
    $rows = '';
    foreach ($data as $r) { $d = date('F d, Y', strtotime($r['date'])); $rows .= "<tr><td>{$d}</td></tr>"; }
    return "<p style='margin:10px 0 5px;font-size:13px;'>The following AWOL (Absent Without Official Leave) dates have been recorded:</p>
        <table class='att-table'><thead><tr><th>Date of Absence (AWOL)</th></tr></thead><tbody>{$rows}</tbody></table>";
}
function buildNotLoggingTable($data) {
    if (empty($data)) return '';
    $rows = '';
    foreach ($data as $r) { $d = date('F d, Y', strtotime($r['date'])); $rows .= "<tr><td>{$d}</td></tr>"; }
    return "<p style='margin:10px 0 5px;font-size:13px;'>The following dates with missing or improper attendance log entries have been recorded:</p>
        <table class='att-table'><thead><tr><th>Date of Improper / Missing Log</th></tr></thead><tbody>{$rows}</tbody></table>";
}

$attendanceDetailLabel = [
    'late'              => 'Repeated late arrivals',
    'awol'              => 'Absent without notice (AWOL)',
    'frequent_absences' => 'Frequent or unexplained absences',
    'early_leave'       => 'Leaving work early without permission',
    'not_logging'       => 'Not logging attendance properly',
][$attendanceDetail] ?? $attendanceDetail;

$offenseCategory  = $report['offense_category'] ?? '';
$offenseCatLabel  = $offenseCategory === 'minor' ? 'Minor Offense' : ($offenseCategory === 'major' ? 'Major Offense' : '');
$evidenceFile     = $report['evidence_file']     ?? '';
$actionTakenOther = $report['action_taken_other'] ?? '';
$suspensionDays   = (int)($report['suspension_days'] ?? 0);

if ($report['action_taken'] === 'Suspension' && $suspensionDays > 0)
    $actionTakenFinal = 'Suspension – ' . $suspensionDays . ' day' . ($suspensionDays > 1 ? 's' : '');
elseif ($report['action_taken'] === 'Other' && $actionTakenOther)
    $actionTakenFinal = $actionTakenOther;
else
    $actionTakenFinal = $report['action_taken'] ?? '';

$nature      = $report['nature'] ?? '';
$natureLabel = ['attendance' => 'Attendance and Punctuality', 'conduct' => 'Conduct and Behavior'][$nature] ?? $nature;
$needsDateTable = in_array($attendanceDetail, ['late', 'awol', 'not_logging']);

$incidentNarrative = '';
if ($nature === 'attendance') {
    $incidentNarrative = "This letter serves as a formal Incident Report concerning your " . strtolower($natureLabel) . " record on " . e($incidentDate) . ".";
    if ($attendanceDetail === 'late') {
        $minutes = $report['minutes_value'] ?? 0;
        $incidentNarrative .= " Based on the attendance log, you arrived late by " . e($minutes) . " minute(s) of tardiness.";
        if ($report['dates_in_month']) {
            $cnt = count(array_filter(array_map('trim', explode(',', $report['dates_in_month']))));
            $incidentNarrative .= " This marks your " . getOrdinalSuffix($cnt) . " late occurrence for the month.";
        }
    } elseif ($attendanceDetail === 'awol') {
        $incidentNarrative .= " You were marked absent without official leave (AWOL) on the date(s) listed below. Absence without notice is a serious violation of company policy.";
    } elseif ($attendanceDetail === 'frequent_absences') {
        $incidentNarrative .= " You have been noted with frequent or unexplained absences.";
    } elseif ($attendanceDetail === 'early_leave') {
        $incidentNarrative .= " You left work early without prior permission or approval.";
    } elseif ($attendanceDetail === 'not_logging') {
        $incidentNarrative .= " You have failed to properly log your attendance as required by company policy on the date(s) listed below. Proper time-keeping is a mandatory requirement.";
    }
} else {
    $incidentNarrative = "This letter serves as a formal Incident Report regarding a conduct matter on " . e($incidentDate) . ".";
    if ($offenseCatLabel) $incidentNarrative .= " Offense Category: " . e($offenseCatLabel) . ".";
    $incidentNarrative .= " The following conduct issue has been documented: " . e($report['conduct_detail'] ?? 'N/A') . ".";
    if ($report['evidence']) $incidentNarrative .= " Details: " . e($report['evidence']);
}

$hasCustomHtml  = !empty($report['custom_html']);
$customHtmlDate = ($hasCustomHtml && !empty($report['custom_html_at']))
    ? date('F d, Y g:i A', strtotime($report['custom_html_at'])) : '';

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Incident Report</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family:'Calibri','Arial',sans-serif;
    background:linear-gradient(14deg,#1d42eb 0%,#490490 100%);
    padding:20px; min-height:100vh; line-height:1.6;
}
.pdf-wrapper { max-width:850px; margin:0 auto; }

/* Action bar */
.action-bar { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.btn-action {
    padding:12px 24px; border:none; border-radius:6px;
    font-size:14px; font-weight:600; cursor:pointer;
    transition:all .3s; display:inline-flex; align-items:center; gap:8px;
}
.btn-action:hover { transform:translateY(-2px); }
.btn-action:disabled { opacity:.7; cursor:not-allowed; transform:none !important; }
.btn-pdf-download { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; box-shadow:0 4px 15px rgba(102,126,234,.4); }
.btn-print  { background:#28a745; color:#fff; box-shadow:0 4px 15px rgba(40,167,69,.4); }
.btn-save   { background:linear-gradient(135deg,#10b981,#059669); color:#fff; box-shadow:0 4px 15px rgba(16,185,129,.4); }
.btn-edit   { background:#f59e0b; color:#fff; box-shadow:0 4px 15px rgba(245,158,11,.4); }
.btn-edit.active { background:#10b981; box-shadow:0 4px 15px rgba(16,185,129,.4); }
.btn-reset  { background:#dc2626; color:#fff; box-shadow:0 4px 15px rgba(220,38,38,.4); }
.btn-close  { background:#6c757d; color:#fff; box-shadow:0 4px 15px rgba(108,117,125,.4); }
.btn-save:disabled { background:#334155; color:#64748b; box-shadow:none; transform:none; cursor:not-allowed; }

/* Version badge */
.custom-html-badge {
    display:flex; align-items:center; gap:8px;
    background:#1e293b; border:1px solid #334155; border-radius:8px;
    padding:8px 14px; margin-bottom:14px; font-size:12.5px; color:#94a3b8;
}
.custom-html-badge .badge-dot { width:8px; height:8px; background:#10b981; border-radius:50%; box-shadow:0 0 6px #10b981; }
.custom-html-badge strong { color:#34d399; }

/* Edit toolbar */
.edit-toolbar { display:none; background:#1e293b; border-radius:8px; margin-bottom:14px; padding:10px 14px; flex-direction:column; gap:10px; }
.edit-toolbar.visible { display:flex; }
.toolbar-guideline { background:#0f172a; border-radius:6px; padding:8px 12px; font-size:11.5px; color:#94a3b8; line-height:1.7; }
.toolbar-guideline strong { color:#e2e8f0; font-size:12px; }
.guide-row { display:flex; flex-wrap:wrap; gap:10px; margin-top:4px; }
.guide-item { display:flex; align-items:center; gap:5px; }
.guide-key { background:#334155; border:1px solid #475569; border-radius:4px; padding:1px 7px; font-size:11px; color:#e2e8f0; font-family:monospace; }
.toolbar-controls { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.toolbar-divider  { width:1px; height:24px; background:#334155; margin:0 4px; }
.toolbar-btn { background:#334155; border:1px solid #475569; color:#e2e8f0; border-radius:5px; padding:5px 11px; font-size:13px; cursor:pointer; transition:all .15s; }
.toolbar-btn:hover { background:#475569; color:#fff; }
.toolbar-btn.pressed { background:#f59e0b; border-color:#f59e0b; color:#000; }
.font-size-control { display:flex; align-items:center; gap:4px; background:#0f172a; border:1px solid #334155; border-radius:5px; padding:3px 6px; }
.font-size-control label { color:#94a3b8; font-size:11px; white-space:nowrap; }
.font-size-control input[type=range] { width:90px; accent-color:#f59e0b; }
.font-size-val { color:#f59e0b; font-size:12px; font-weight:600; min-width:28px; text-align:right; }
.font-family-control { display:flex; align-items:center; gap:5px; background:#0f172a; border:1px solid #334155; border-radius:5px; padding:3px 8px; }
.font-family-control label { color:#94a3b8; font-size:11px; white-space:nowrap; }
.font-family-control select { background:#1e293b; border:1px solid #475569; color:#e2e8f0; border-radius:4px; font-size:12px; padding:2px 4px; cursor:pointer; outline:none; }
.toolbar-status { margin-left:auto; font-size:11.5px; color:#64748b; font-style:italic; }

/* PDF container */
.pdf-container { background:#fff; padding:50px 60px; box-shadow:0 10px 40px rgba(0,0,0,.15); line-height:1.8; color:#333; }
.pdf-container.edit-mode-on { outline:2px solid #f59e0b; cursor:text; caret-color:#333; }

/* Typography */
.date-section, .recipient-section { margin-bottom:22px; font-size:13px; line-height:1.8; }
.recipient-section p { margin:3px 0; }
.subject-line { margin:22px 0; font-size:13px; }
.letter-body  { font-size:13px; line-height:1.8; text-align:justify; margin-bottom:22px; }
.letter-body p { margin-bottom:14px; text-align:justify; }
.closing { margin-top:36px; font-size:13px; }
.sig-gap  { margin-top:48px; }
.sig-line { display:inline-block; width:220px; border-top:1px solid #000; margin-bottom:4px; }

/* Attendance tables */
.att-table { width:100%; border-collapse:collapse; margin:4px 0 18px; font-size:13px; border:1px solid #2d3748; }
.att-table thead tr { background:#2d3748; color:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
.att-table th { padding:10px 14px; font-weight:600; border:1px solid #2d3748; text-align:left; }
.att-table th:last-child, .att-table td:last-child { text-align:center; }
.att-table td { padding:9px 14px; border:1px solid #cbd5e0; }
.att-table tbody tr:nth-child(even) { background:#f8fafc; }

/* Incident details */
.incident-details { font-size:13px; margin:12px 0 16px; }
.incident-details p { margin:7px 0; }
.offense-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.offense-minor { color:#92400e; }
.offense-major { color:#991b1b; }

/* Acknowledgment */
.ack-section { margin-top:50px; padding:20px; border:1px solid #000; font-size:12px; line-height:1.8; page-break-inside:avoid; break-inside:avoid; }
.ack-section p { margin:8px 0; }
.sig-line-ack { display:inline-block; width:180px; border-top:1px solid #000; margin-top:8px; vertical-align:bottom; }

/* Evidence section */
.evidence-section { margin-top:22px; border:1px dashed #999; font-size:12px; line-height:1.9; }
.evidence-header { background:#f1f5f9; padding:9px 20px; font-size:12px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; border-bottom:1px dashed #999; color:#334155; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
.evidence-body { padding:14px 20px; }
.evidence-body p { margin:5px 0; }

/* ── PDF Evidence: SCREEN viewer ── */
.evidence-pdf-screen {
    margin-top:10px;
    border:1px solid #e2e8f0;
    border-radius:8px;
    overflow:hidden;
    background:#f8fafc;
}
.pdf-screen-toolbar {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    padding:8px 14px; background:#1e293b; border-bottom:1px solid #334155;
}
.pdf-screen-toolbar span { color:#94a3b8; font-size:12px; }
.pdf-screen-toolbar strong { color:#e2e8f0; font-size:12px; }
.pdf-nav-btn {
    padding:4px 12px; border:1px solid #475569; border-radius:4px;
    background:#334155; color:#e2e8f0; cursor:pointer; font-size:12px;
    transition:background .15s;
}
.pdf-nav-btn:hover { background:#475569; }
.pdf-nav-btn:disabled { opacity:.4; cursor:not-allowed; }
.pdf-open-link {
    margin-left:auto; padding:4px 12px; background:#1d42eb; color:#fff;
    border-radius:4px; text-decoration:none; font-size:12px; font-weight:600;
}
.pdf-screen-canvas-wrap {
    padding:16px; display:flex; justify-content:center; background:#525659;
    min-height:200px; align-items:center;
}
.pdf-screen-canvas-wrap canvas {
    max-width:100%; box-shadow:0 4px 20px rgba(0,0,0,.4);
    display:block;
}
.pdf-loading-msg { color:#e2e8f0; font-size:13px; text-align:center; padding:40px; }
.pdf-loading-msg .spinner {
    display:inline-block; width:18px; height:18px;
    border:2px solid #475569; border-top-color:#667eea;
    border-radius:50%; animation:spin .7s linear infinite;
    vertical-align:middle; margin-right:8px;
}
@keyframes spin { to { transform:rotate(360deg); } }

/* ── PDF Evidence: PRINT canvases (hidden on screen) ── */
.evidence-pdf-canvases { display:none; }
.evidence-pdf-canvases .pdf-page-wrap { margin-bottom:8mm; page-break-inside:avoid; break-inside:avoid; }
.evidence-pdf-canvases .pdf-page-num { font-size:10px; color:#64748b; text-align:right; margin-bottom:2px; font-style:italic; }
.evidence-pdf-canvases canvas { width:100%; display:block; border:1px solid #ddd; }

/* Toast */
.save-toast { display:none; position:fixed; bottom:28px; right:28px; padding:14px 22px; border-radius:10px; font-size:14px; font-weight:600; z-index:9999; box-shadow:0 8px 24px rgba(0,0,0,.3); animation:toastIn .3s ease; }
.save-toast.success { background:#10b981; color:#fff; }
.save-toast.error   { background:#ef4444; color:#fff; }
.save-toast.show    { display:block; }
@keyframes toastIn { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }

/* Modals */
.confirm-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); backdrop-filter:blur(3px); z-index:9999; align-items:center; justify-content:center; }
.confirm-overlay.show { display:flex; }
.confirm-modal { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:32px 28px 24px; max-width:380px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.5); text-align:center; animation:modalPop .2s ease; }
@keyframes modalPop { from{transform:scale(.85);opacity:0} to{transform:scale(1);opacity:1} }
.confirm-icon    { font-size:42px; margin-bottom:12px; }
.confirm-title   { font-size:17px; font-weight:700; color:#f1f5f9; margin-bottom:8px; }
.confirm-message { font-size:13px; color:#94a3b8; margin-bottom:24px; line-height:1.6; }
.confirm-buttons { display:flex; gap:10px; justify-content:center; }
.confirm-btn { padding:10px 28px; border:none; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; transition:all .2s; }
.confirm-btn-cancel  { background:#334155; color:#cbd5e1; border:1px solid #475569; }
.confirm-btn-cancel:hover { background:#475569; color:#fff; }
.confirm-btn-confirm { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; box-shadow:0 4px 14px rgba(239,68,68,.4); }
.confirm-btn-confirm:hover { background:linear-gradient(135deg,#dc2626,#b91c1c); transform:translateY(-1px); }

/* ══════════════════════ PRINT ══════════════════════ */
@media print {
    html, body { padding:0!important; margin:0!important; background:#fff!important; -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
    .pdf-wrapper { max-width:100%; margin:0!important; }
    .action-bar, .edit-toolbar, .custom-html-badge, .save-toast, .confirm-overlay { display:none!important; }
    .pdf-container { box-shadow:none!important; padding:0!important; margin:0!important; font-size:11px!important; line-height:1.5!important; }
    .letter-body p { orphans:3; widows:3; }
    .ack-section { page-break-inside:avoid!important; break-inside:avoid!important; margin-top:20px!important; }
    .evidence-section { page-break-before:always!important; break-before:page!important; margin-top:0!important; border-top:none!important; }

    /* Hide screen viewer, show print canvases */
    .evidence-pdf-screen    { display:none!important; }
    .evidence-pdf-canvases  { display:block!important; }
    .evidence-pdf-canvases .pdf-page-wrap { page-break-inside:avoid; break-inside:avoid; margin-bottom:6mm; }
    .evidence-pdf-canvases canvas { width:100%!important; max-width:100%!important; display:block!important; border:none!important; }

    .att-table thead tr, .evidence-header { -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
    @page { size:A4; margin:15mm; }
}
</style>
</head>
<body>
<div class="pdf-wrapper">

    <!-- Action Bar -->
    <div class="action-bar">
        <button class="btn-action btn-pdf-download" id="downloadBtn" onclick="downloadPDF()">📥 Download as PDF</button>
        <button class="btn-action btn-print" id="printBtn" onclick="printPage()">🖨️ Print</button>
        <button class="btn-action btn-save" id="saveBtn" onclick="saveEditedContent()">💾 Save Edits</button>
        <button class="btn-action btn-edit" id="editBtn" onclick="toggleEditMode()">✏️ Edit</button>
        <?php if ($hasCustomHtml): ?>
        <button class="btn-action btn-reset" onclick="confirmReset()">↺ Reset to Original</button>
        <?php endif; ?>
        <button class="btn-action btn-close" onclick="closeWindow()">← Back to Employee</button>
    </div>

    <?php if ($hasCustomHtml): ?>
    <div class="custom-html-badge">
        <span class="badge-dot"></span>
        <span>Showing <strong>saved edited version</strong><?= $customHtmlDate ? ' — last saved ' . e($customHtmlDate) : '' ?>. Click <strong>Reset to Original</strong> to revert.</span>
    </div>
    <?php endif; ?>

    <!-- Edit Toolbar -->
    <div class="edit-toolbar" id="editToolbar">
        <div class="toolbar-guideline">
            <strong>📋 Editing Guidelines</strong>
            <div class="guide-row">
                <div class="guide-item"><span class="guide-key">Ctrl+B</span> Bold</div>
                <div class="guide-item"><span class="guide-key">Ctrl+I</span> Italic</div>
                <div class="guide-item"><span class="guide-key">Ctrl+U</span> Underline</div>
                <div class="guide-item"><span class="guide-key">Ctrl+Z</span> Undo</div>
                <div class="guide-item"><span class="guide-key">Ctrl+]</span> Bigger text</div>
                <div class="guide-item"><span class="guide-key">Ctrl+[</span> Smaller text</div>
                <div class="guide-item"><span class="guide-key">💾 Save Edits</span> Persist changes to the database</div>
            </div>
        </div>
        <div class="toolbar-controls">
            <button class="toolbar-btn" id="btnBold"      onmousedown="event.preventDefault();applyFormat('bold')"><b>B</b></button>
            <button class="toolbar-btn" id="btnItalic"    onmousedown="event.preventDefault();applyFormat('italic')"><i>I</i></button>
            <button class="toolbar-btn" id="btnUnderline" onmousedown="event.preventDefault();applyFormat('underline')"><u>U</u></button>
            <div class="toolbar-divider"></div>
            <div class="font-family-control">
                <label>Font:</label>
                <select id="fontFamilySelect" onchange="applyFontFamily(this.value)">
                    <option value="Calibri, Arial, sans-serif">Calibri</option>
                    <option value="Arial, sans-serif">Arial</option>
                    <option value="'Times New Roman', serif">Times New Roman</option>
                    <option value="Georgia, serif">Georgia</option>
                    <option value="'Courier New', monospace">Courier New</option>
                    <option value="Verdana, sans-serif">Verdana</option>
                    <option value="Tahoma, sans-serif">Tahoma</option>
                    <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
                </select>
            </div>
            <div class="toolbar-divider"></div>
            <div class="font-size-control">
                <label>Size:</label>
                <input type="range" id="fontSizeSlider" min="8" max="24" value="13" oninput="applyFontSize(this.value)">
                <span class="font-size-val" id="fontSizeVal">13px</span>
            </div>
            <div class="toolbar-divider"></div>
            <button class="toolbar-btn" onmousedown="event.preventDefault();applyFormat('removeFormat')">✕ Clear</button>
            <span class="toolbar-status" id="toolbarStatus">Select text to apply formatting</span>
        </div>
    </div>

    <!-- PDF CONTENT -->
    <div class="pdf-container" id="pdfContent">

        <?php if ($hasCustomHtml): ?>
            <?= $report['custom_html'] ?>
        <?php else: ?>

            <div class="date-section"><p>Date: <?= e($submittedDate) ?></p></div>

            <div class="recipient-section">
                <p><strong><?= e($fullName) ?></strong></p>
                <p><?= e($report['position']) ?></p>
                <p><?= e($report['department']) ?></p>
            </div>

            <div class="subject-line">
                <p><strong>Subject: Formal Incident Report – <?= e($natureLabel) ?></strong></p>
            </div>

            <p style="font-size:13px;margin-bottom:16px;">Dear <?= e(explode(' ', $fullName)[0]) ?>,</p>

            <div class="letter-body">

                <?php if ($nature === 'attendance'): ?>
                    <p><?= e($incidentNarrative) ?></p>

                    <?php if ($needsDateTable && empty($datesData)): ?>
                        <?php if ($attendanceDetail === 'late'): ?>
                            <p>Based on the attendance log, you arrived late resulting in <?= e($report['minutes_value'] ?? 0) ?> minute(s) of tardiness on <?= e($incidentDate) ?>. This incident has been documented in your personnel file.</p>
                        <?php elseif ($attendanceDetail === 'awol'): ?>
                            <p><strong>Date of AWOL:</strong> <?= e($incidentDate) ?></p>
                        <?php elseif ($attendanceDetail === 'not_logging'): ?>
                            <p><strong>Date of Missing/Improper Log:</strong> <?= e($incidentDate) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="incident-details">
                        <p><strong>Attendance Issue:</strong> <?= e($attendanceDetailLabel) ?></p>
                        <?php if ($offenseCatLabel): ?>
                        <p><strong>Offense Category:</strong> <span class="offense-badge offense-<?= e($offenseCategory) ?>"><?= e($offenseCatLabel) ?></span></p>
                        <?php endif; ?>
                        <?php if ($actionTakenFinal): ?>
                        <p><strong>Immediate Action Taken:</strong> <?= e($actionTakenFinal) ?></p>
                        <?php endif; ?>
                        <?php if ($report['recommendations']): ?>
                        <p><strong>Recommendations/Follow-up:</strong> <?= e($report['recommendations']) ?></p>
                        <?php endif; ?>
                    </div>

                    <p>It is critical that you understand that habitual tardiness and attendance violations negatively affect team operations, productivity, and overall workplace efficiency. While we understand that unforeseen circumstances may occur, employees are expected to notify their immediate supervisor in advance whenever possible.</p>
                    <p>You are hereby requested to submit a written explanation within twenty-four (24) hours upon receipt of this notice. Further violations of company attendance policies may lead to more serious disciplinary action in accordance with company rules and regulations.</p>
                    <p>We trust that you will take the necessary corrective measures to improve your attendance and adhere strictly to company policies moving forward.</p>

                <?php else: ?>
                    <p><?= e($incidentNarrative) ?></p>
                    <p>The following details have been recorded regarding this matter:</p>
                    <div class="incident-details">
                        <p><strong>Date of Incident:</strong> <?= e($incidentDate) ?></p>
                        <?php if ($offenseCatLabel): ?>
                        <p><strong>Offense Category:</strong> <span class="offense-badge offense-<?= e($offenseCategory) ?>"><?= e($offenseCatLabel) ?></span></p>
                        <?php endif; ?>
                        <p><strong>Type of Violation:</strong> <?= e($report['conduct_detail'] ?? 'N/A') ?></p>
                        <?php if ($report['evidence']): ?><p><strong>Evidence/Details:</strong> <?= e($report['evidence']) ?></p><?php endif; ?>
                        <?php if ($evidenceFile): ?><p><strong>Attached File:</strong> 📎 <?= e(basename($evidenceFile)) ?></p><?php endif; ?>
                        <?php if ($actionTakenFinal): ?><p><strong>Immediate Action Taken:</strong> <?= e($actionTakenFinal) ?></p><?php endif; ?>
                        <?php if ($report['recommendations']): ?><p><strong>Recommendations/Follow-up:</strong> <?= e($report['recommendations']) ?></p><?php endif; ?>
                    </div>
                    <p>This conduct must be corrected immediately. Continued violations may result in further disciplinary action up to and including termination of employment.</p>
                    <p>You are required to meet with the Human Resources Department within forty-eight (48) hours to discuss this matter and to ensure your understanding of the company's conduct expectations.</p>
                <?php endif; ?>

                <p>Should you wish to discuss this matter, you may coordinate with the Human Resources Department.</p>
                <p>Thank you for your prompt attention to this matter.</p>

            </div>

            <div class="closing">
                <p>Sincerely,</p>
                <div class="sig-gap">
                    <div class="sig-line"></div>
                    <p>Human Resources Department</p>
                </div>
            </div>

            <!-- ACKNOWLEDGMENT -->
            <div class="ack-section">
                <p><strong>EMPLOYEE ACKNOWLEDGMENT:</strong></p>
                <p>I acknowledge receipt of this Incident Report.</p>
                <p><strong>Employee Signature:</strong> <span class="sig-line-ack"></span></p>
                <p><strong>Date Received:</strong> <span class="sig-line-ack"></span></p>
            </div>

            <!-- EVIDENCE -->
            <?php if ($needsDateTable || $report['evidence'] || $evidenceFile): ?>
            <div class="evidence-section">
                <div class="evidence-header">Supporting Evidence &amp; Documentation</div>
                <div class="evidence-body">

                    <?php if ($needsDateTable && !empty($datesData)): ?>
                    <div style="margin-bottom:16px;">
                        <?php if ($attendanceDetail === 'late'):        echo buildTardinessTable($datesData); endif; ?>
                        <?php if ($attendanceDetail === 'awol'):        echo buildAwolTable($datesData);      endif; ?>
                        <?php if ($attendanceDetail === 'not_logging'): echo buildNotLoggingTable($datesData); endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($report['evidence']): ?>
                    <p><strong>Description:</strong> <?= e($report['evidence']) ?></p>
                    <?php endif; ?>

                    <?php if ($evidenceFile): ?>
                    <?php
                        $ext     = strtolower(pathinfo($evidenceFile, PATHINFO_EXTENSION));
                        $fullUrl = '/employee201/' . $evidenceFile;
                    ?>
                    <div style="margin-top:12px;">
                        <p style="margin-bottom:8px;"><strong>Attached File:</strong> 📎 <?= e(basename($evidenceFile)) ?></p>

                        <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                            <!-- IMAGE — always printable -->
                            <img src="<?= e($fullUrl) ?>" alt="Evidence"
                                 style="max-width:100%;max-height:600px;border:1px solid #ccc;border-radius:6px;display:block;">

                        <?php elseif ($ext === 'pdf'): ?>

                            <!-- SCREEN: clean dark-toolbar canvas viewer -->
                            <div class="evidence-pdf-screen" id="pdfScreenViewer">
                                <div class="pdf-screen-toolbar">
                                    <button class="pdf-nav-btn" id="pdfPrevBtn" onclick="pdfNavPrev()" disabled>◀ Prev</button>
                                    <strong id="pdfNavLabel" style="color:#e2e8f0;font-size:12px;">Loading…</strong>
                                    <button class="pdf-nav-btn" id="pdfNextBtn" onclick="pdfNavNext()" disabled>Next ▶</button>
                                    <a class="pdf-open-link" href="<?= e($fullUrl) ?>" target="_blank">🔗 Open PDF</a>
                                </div>
                                <div class="pdf-screen-canvas-wrap" id="pdfScreenWrap">
                                    <div class="pdf-loading-msg" id="pdfLoadingMsg">
                                        <span class="spinner"></span> Loading PDF…
                                    </div>
                                </div>
                            </div>

                            <!-- PRINT / DOWNLOAD: all pages as canvases -->
                            <div class="evidence-pdf-canvases" id="pdfPrintCanvases"
                                 data-pdf-url="<?= e($fullUrl) ?>">
                                <p style="font-size:11px;color:#64748b;margin-bottom:6px;font-style:italic;">
                                    Attached PDF Evidence: <?= e(basename($evidenceFile)) ?>
                                </p>
                            </div>

                        <?php else: ?>
                            <a href="<?= e($fullUrl) ?>" target="_blank"
                               style="display:inline-block;padding:8px 16px;background:#1d42eb;color:#fff;border-radius:5px;text-decoration:none;font-size:13px;">
                                📥 Download <?= e(strtoupper($ext)) ?> File
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!$report['evidence'] && !$evidenceFile && empty($datesData)): ?>
                    <p style="color:#888;font-style:italic;">No additional evidence or documentation on file.</p>
                    <?php endif; ?>

                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div><!-- /pdfContent -->

</div><!-- /pdf-wrapper -->

<div class="save-toast" id="saveToast"></div>

<!-- Close modal -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-modal">
        <div class="confirm-icon">⚠️</div>
        <div class="confirm-title">Go Back to Employee?</div>
        <div class="confirm-message">Are you sure you want to go back?<br>Any <strong style="color:#f87171">unsaved edits</strong> will be lost.</div>
        <div class="confirm-buttons">
            <button class="confirm-btn confirm-btn-cancel" onclick="cancelClose()">Cancel</button>
            <button class="confirm-btn confirm-btn-confirm" onclick="confirmClose()">Yes, Close</button>
        </div>
    </div>
</div>

<!-- Reset modal -->
<div class="confirm-overlay" id="resetOverlay">
    <div class="confirm-modal">
        <div class="confirm-icon">↺</div>
        <div class="confirm-title">Reset to Original?</div>
        <div class="confirm-message">This will delete your saved edits and restore the auto-generated version.<br>This <strong style="color:#f87171">cannot be undone</strong>.</div>
        <div class="confirm-buttons">
            <button class="confirm-btn confirm-btn-cancel" onclick="document.getElementById('resetOverlay').classList.remove('show')">Cancel</button>
            <button class="confirm-btn confirm-btn-confirm" onclick="doReset()">Yes, Reset</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const REPORT_ID = <?= $report_id ?>;
const FULL_NAME = <?= json_encode($fullName) ?>;
let editMode = false, hasUnsaved = false;

// ════════════════════════════════════════════════════════
// PDF.js evidence renderer
// ════════════════════════════════════════════════════════
let pdfDoc = null, pdfCurrentPage = 1, pdfTotalPages = 0, pdfCanvasesReady = false;

async function initEvidencePDF() {
    const printDiv = document.getElementById('pdfPrintCanvases');
    if (!printDiv) { pdfCanvasesReady = true; return; }

    const url        = printDiv.dataset.pdfUrl;
    const screenWrap = document.getElementById('pdfScreenWrap');
    const loadingMsg = document.getElementById('pdfLoadingMsg');
    const navLabel   = document.getElementById('pdfNavLabel');
    const prevBtn    = document.getElementById('pdfPrevBtn');
    const nextBtn    = document.getElementById('pdfNextBtn');

    try {
        pdfDoc        = await pdfjsLib.getDocument(url).promise;
        pdfTotalPages = pdfDoc.numPages;

        // ── Screen viewer: render first page ──
        if (loadingMsg) loadingMsg.remove();
        await renderScreenPage(pdfCurrentPage);

        if (navLabel) navLabel.textContent = 'Page ' + pdfCurrentPage + ' of ' + pdfTotalPages;
        if (prevBtn)  prevBtn.disabled  = (pdfCurrentPage <= 1);
        if (nextBtn)  nextBtn.disabled  = (pdfCurrentPage >= pdfTotalPages);

        // ── Print canvases: render ALL pages (hidden) ──
        for (let n = 1; n <= pdfTotalPages; n++) {
            const page     = await pdfDoc.getPage(n);
            const viewport = page.getViewport({ scale: 2.0 });
            const wrap     = document.createElement('div');
            wrap.className = 'pdf-page-wrap';
            const lbl = document.createElement('p');
            lbl.className   = 'pdf-page-num';
            lbl.textContent = 'Page ' + n + ' of ' + pdfTotalPages;
            const canvas    = document.createElement('canvas');
            canvas.width    = viewport.width;
            canvas.height   = viewport.height;
            wrap.appendChild(lbl);
            wrap.appendChild(canvas);
            printDiv.appendChild(wrap);
            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        }

        pdfCanvasesReady = true;

    } catch (err) {
        console.warn('pdf.js error:', err);
        if (screenWrap) screenWrap.innerHTML = '<p style="color:#fca5a5;padding:20px;font-size:13px;">⚠️ Could not render PDF. <a href="' + url + '" target="_blank" style="color:#93c5fd;">Open PDF ↗</a></p>';
        pdfCanvasesReady = true;
    }
}

async function renderScreenPage(n) {
    const screenWrap = document.getElementById('pdfScreenWrap');
    if (!screenWrap || !pdfDoc) return;
    screenWrap.innerHTML = '';
    const page       = await pdfDoc.getPage(n);
    const containerW = screenWrap.offsetWidth || 700;
    const baseVP     = page.getViewport({ scale: 1 });
    const scale      = Math.min((containerW - 32) / baseVP.width, 2.0);
    const viewport   = page.getViewport({ scale });
    const canvas     = document.createElement('canvas');
    canvas.width     = viewport.width;
    canvas.height    = viewport.height;
    canvas.style.cssText = 'max-width:100%;display:block;';
    screenWrap.appendChild(canvas);
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
}

async function pdfNavPrev() {
    if (!pdfDoc || pdfCurrentPage <= 1) return;
    pdfCurrentPage--;
    await renderScreenPage(pdfCurrentPage);
    updateNavButtons();
}
async function pdfNavNext() {
    if (!pdfDoc || pdfCurrentPage >= pdfTotalPages) return;
    pdfCurrentPage++;
    await renderScreenPage(pdfCurrentPage);
    updateNavButtons();
}
function updateNavButtons() {
    const navLabel = document.getElementById('pdfNavLabel');
    const prevBtn  = document.getElementById('pdfPrevBtn');
    const nextBtn  = document.getElementById('pdfNextBtn');
    if (navLabel) navLabel.textContent = 'Page ' + pdfCurrentPage + ' of ' + pdfTotalPages;
    if (prevBtn)  prevBtn.disabled  = (pdfCurrentPage <= 1);
    if (nextBtn)  nextBtn.disabled  = (pdfCurrentPage >= pdfTotalPages);
}

function waitForCanvases() {
    if (!document.getElementById('pdfPrintCanvases')) { pdfCanvasesReady = true; return Promise.resolve(); }
    return new Promise(resolve => {
        const start = Date.now();
        const id = setInterval(() => {
            if (pdfCanvasesReady || Date.now() - start > 10000) { clearInterval(id); resolve(); }
        }, 100);
    });
}

function showPdfCanvases() {
    const s = document.getElementById('pdfScreenViewer');
    const p = document.getElementById('pdfPrintCanvases');
    if (s) s.style.display = 'none';
    if (p) p.style.display = 'block';
}
function hidePdfCanvases() {
    const s = document.getElementById('pdfScreenViewer');
    const p = document.getElementById('pdfPrintCanvases');
    if (s) s.style.display = '';
    if (p) p.style.display = 'none';
}

// Start rendering on load
initEvidencePDF();

// ════════════════════════════════════════════════════════
// Edit mode
// ════════════════════════════════════════════════════════
function toggleEditMode() {
    editMode = !editMode;
    const btn = document.getElementById('editBtn');
    const tb  = document.getElementById('editToolbar');
    const box = document.getElementById('pdfContent');
    if (editMode) {
        box.contentEditable = 'true'; box.classList.add('edit-mode-on'); box.focus();
        btn.textContent = '✅ Done Editing'; btn.classList.add('active'); tb.classList.add('visible');
        box.addEventListener('input', markUnsaved);
    } else {
        box.contentEditable = 'false'; box.classList.remove('edit-mode-on');
        btn.textContent = '✏️ Edit'; btn.classList.remove('active'); tb.classList.remove('visible');
        box.removeEventListener('input', markUnsaved);
    }
}
function markUnsaved() { hasUnsaved = true; document.getElementById('saveBtn').textContent = '💾 Save Edits *'; }

function applyFormat(cmd) {
    if (!editMode) return;
    document.execCommand(cmd, false, null); updateToolbarState();
    document.getElementById('pdfContent').focus();
}
function applyFontSize(size) {
    if (!editMode) return;
    document.getElementById('fontSizeVal').textContent = size + 'px';
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0 && !sel.isCollapsed) {
        const range = sel.getRangeAt(0), span = document.createElement('span');
        span.style.fontSize = size + 'px';
        try { range.surroundContents(span); } catch(e) {
            document.execCommand('fontSize', false, '7');
            document.querySelectorAll('font[size="7"]').forEach(el => { el.style.fontSize = size+'px'; el.removeAttribute('size'); });
        }
    }
    document.getElementById('pdfContent').focus();
}
function applyFontFamily(font) {
    if (!editMode) return;
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0 && !sel.isCollapsed) {
        const range = sel.getRangeAt(0), span = document.createElement('span');
        span.style.fontFamily = font;
        try { range.surroundContents(span); } catch(e) { document.execCommand('fontName', false, font); }
    } else { document.getElementById('pdfContent').style.fontFamily = font; }
    document.getElementById('pdfContent').focus();
}
function updateToolbarState() {
    document.getElementById('btnBold').classList.toggle('pressed',      document.queryCommandState('bold'));
    document.getElementById('btnItalic').classList.toggle('pressed',    document.queryCommandState('italic'));
    document.getElementById('btnUnderline').classList.toggle('pressed', document.queryCommandState('underline'));
}
document.addEventListener('selectionchange', () => {
    if (!editMode) return;
    updateToolbarState();
    const sel = window.getSelection(), st = document.getElementById('toolbarStatus');
    if (sel && !sel.isCollapsed) { st.textContent = '✅ Text selected — apply formatting above'; st.style.color = '#34d399'; }
    else { st.textContent = 'Select text to apply Bold / Italic / Underline / Size'; st.style.color = '#64748b'; }
});
document.addEventListener('keydown', function(e) {
    if (!editMode) return;
    if (e.ctrlKey && ['b','i','u'].includes(e.key)) setTimeout(updateToolbarState, 10);
    if (e.ctrlKey && (e.key === ']' || e.key === '[')) {
        e.preventDefault();
        const sl = document.getElementById('fontSizeSlider');
        let v = parseInt(sl.value);
        v = e.key === ']' ? Math.min(24, v+1) : Math.max(8, v-1);
        sl.value = v; applyFontSize(v);
    }
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); saveEditedContent(); }
});

// ════════════════════════════════════════════════════════
// Toast / Save / Reset
// ════════════════════════════════════════════════════════
function showToast(msg, type='success') {
    const t = document.getElementById('saveToast');
    t.textContent = msg; t.className = 'save-toast show ' + type;
    setTimeout(() => { t.className = 'save-toast'; }, 3500);
}
async function saveEditedContent() {
    if (editMode) toggleEditMode();
    const btn = document.getElementById('saveBtn');
    btn.disabled = true; btn.textContent = '⏳ Saving...';
    const html = document.getElementById('pdfContent').innerHTML;
    try {
        const res = await fetch('save_report_content.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ report_id: REPORT_ID, html })
        });
        const r = await res.json();
        if (r.ok) {
            hasUnsaved = false; showToast('✅ Edits saved!', 'success');
            btn.textContent = '✅ Saved';
            setTimeout(() => { btn.disabled = false; btn.textContent = '💾 Save Edits'; }, 3000);
        } else { showToast('❌ Failed: ' + (r.message||'Unknown error'), 'error'); btn.disabled=false; btn.textContent='💾 Save Edits'; }
    } catch(err) { showToast('❌ Network error: ' + err.message, 'error'); btn.disabled=false; btn.textContent='💾 Save Edits'; }
}
function confirmReset() { document.getElementById('resetOverlay').classList.add('show'); }
async function doReset() {
    document.getElementById('resetOverlay').classList.remove('show');
    try {
        const res = await fetch('save_report_content.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ report_id: REPORT_ID, html:'', reset:true }) });
        const r = await res.json();
        if (r.ok) { showToast('↺ Reset. Reloading…','success'); setTimeout(()=>location.reload(),1200); }
        else showToast('❌ Reset failed: '+(r.message||'Unknown error'),'error');
    } catch(err) { showToast('❌ Network error: '+err.message,'error'); }
}

// ════════════════════════════════════════════════════════
// Print & Download
// ════════════════════════════════════════════════════════
async function printPage() {
    if (editMode) toggleEditMode();
    const btn = document.getElementById('printBtn');
    btn.disabled = true; btn.textContent = '⏳ Preparing...';
    showPdfCanvases();
    try { await waitForCanvases(); } catch(e) {}
    setTimeout(() => {
        window.print();
        hidePdfCanvases();
        btn.disabled = false; btn.textContent = '🖨️ Print';
    }, 400);
}
async function downloadPDF() {
    if (editMode) toggleEditMode();
    const btn = document.getElementById('downloadBtn');
    btn.disabled = true; btn.textContent = '⏳ Generating...';
    showPdfCanvases();
    try { await waitForCanvases(); } catch(e) {}
    const ts = new Date().toISOString().slice(0,10).replace(/-/g,'');
    const fn = 'Incident_Report_' + FULL_NAME.replace(/\s+/g,'_') + '_' + ts + '.pdf';
    html2pdf().set({
        margin:[15,15,15,15], filename:fn,
        image:{type:'jpeg',quality:.98},
        html2canvas:{scale:2,useCORS:true,allowTaint:true,logging:false},
        jsPDF:{orientation:'portrait',unit:'mm',format:'a4'},
        pagebreak:{mode:['avoid-all','css','legacy']}
    }).from(document.getElementById('pdfContent')).save()
    .then(() => { hidePdfCanvases(); btn.disabled=false; btn.textContent='📥 Download as PDF'; })
    .catch(err => { console.error(err); hidePdfCanvases(); btn.disabled=false; btn.textContent='📥 Download as PDF'; });
}

// ════════════════════════════════════════════════════════
// Close
// ════════════════════════════════════════════════════════
function closeWindow()  { document.getElementById('confirmOverlay').classList.add('show'); }
function cancelClose()  { document.getElementById('confirmOverlay').classList.remove('show'); }
function confirmClose() { window.location.href = 'view_employee.php?id=<?= (int)($report['employee_id'] ?? 0) ?>'; }
['confirmOverlay','resetOverlay'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) { if (e.target===this) this.classList.remove('show'); });
});
window.addEventListener('beforeunload', function(e) { if (hasUnsaved) { e.preventDefault(); e.returnValue=''; } });
window.addEventListener('beforeprint', showPdfCanvases);
window.addEventListener('afterprint',  hidePdfCanvases);
</script>
</body>
</html>
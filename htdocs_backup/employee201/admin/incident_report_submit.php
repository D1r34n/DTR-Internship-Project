<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/employee201/includes/auth.php';
include $_SERVER['DOCUMENT_ROOT'] . '/employee201/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['ok' => false, 'message' => 'Invalid request method']));
}

// ── Helper ─────────────────────────────────────────────────────────────
function jsonExit(bool $ok, string $message, array $extra = []): void {
    header('Content-Type: application/json');
    die(json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra)));
}

// ── Core fields ────────────────────────────────────────────────────────
$employee_id        = (int)($_POST['employee_id'] ?? 0);
$report_id          = isset($_POST['report_id']) && is_numeric($_POST['report_id'])
                      ? (int)$_POST['report_id'] : null;
$incident_datetime  = trim($_POST['incident_datetime']  ?? '');
$nature             = trim($_POST['nature']             ?? '');
$attendance_detail  = trim($_POST['attendance_detail']  ?? '');
$offense_category   = trim($_POST['offense_category']   ?? '');
$conduct_detail     = trim($_POST['conduct_detail']     ?? '');
$minutes_value      = (int)($_POST['minutes_value']     ?? 0);
$dates_in_month     = trim($_POST['dates_in_month']     ?? '');
$minutes_data       = trim($_POST['minutes_data']       ?? '[]'); // ← was missing from original INSERT
$evidence           = trim($_POST['evidence']           ?? '');
$action_taken       = trim($_POST['action_taken']       ?? '');
$action_taken_other = trim($_POST['action_taken_other'] ?? '');
$suspension_days    = (int)($_POST['suspension_days']   ?? 0);
$recommendations    = trim($_POST['recommendations']    ?? '');

// ── Resolve final action label ─────────────────────────────────────────
if ($action_taken === 'Other' && $action_taken_other) {
    $action_taken_final = $action_taken_other;
} elseif ($action_taken === 'Suspension' && $suspension_days > 0) {
    $action_taken_final = 'Suspension – ' . $suspension_days . ' day' . ($suspension_days > 1 ? 's' : '');
} else {
    $action_taken_final = $action_taken;
}

// ── Basic validation ───────────────────────────────────────────────────
if (!$employee_id || !$incident_datetime || !$nature) {
    jsonExit(false, 'Missing required fields: employee_id, incident_datetime, or nature.');
}

$is_update = ($report_id !== null);

// ── If updating: verify the report belongs to this employee ────────────
if ($is_update) {
    $chk = $conn->prepare("SELECT id FROM incident_reports WHERE id = ? AND employee_id = ?");
    $chk->bind_param("ii", $report_id, $employee_id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        $chk->close();
        jsonExit(false, 'Report not found or access denied.');
    }
    $chk->close();
}

// ── File upload ────────────────────────────────────────────────────────
$evidence_file      = null; // null = no new file uploaded
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];

if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/employee201/uploads/evidence/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext = strtolower(pathinfo($_FILES['evidence_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions, true)) {
        jsonExit(false, 'File type not allowed. Allowed: ' . implode(', ', $allowed_extensions));
    }
    if ($_FILES['evidence_file']['size'] > 10 * 1024 * 1024) {
        jsonExit(false, 'File exceeds 10 MB limit.');
    }

    $filename = 'evidence_' . $employee_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $filepath)) {
        $evidence_file = 'uploads/evidence/' . $filename;
    } else {
        jsonExit(false, 'Failed to save uploaded file. Check folder permissions.');
    }
}

// ── Admin session ID ───────────────────────────────────────────────────
$admin_session_id = $_SESSION['admin_id'] ?? null;

// ══════════════════════════════════════════════════════════════════════
//  UPDATE existing report
// ══════════════════════════════════════════════════════════════════════
if ($is_update) {

    if ($evidence_file !== null) {
        // New file uploaded → delete old file from disk first
        $old = $conn->prepare("SELECT evidence_file FROM incident_reports WHERE id = ?");
        $old->bind_param("i", $report_id);
        $old->execute();
        $old->bind_result($old_file);
        $old->fetch();
        $old->close();

        if (!empty($old_file)) {
            $full_old = $_SERVER['DOCUMENT_ROOT'] . '/employee201/' . $old_file;
            if (file_exists($full_old)) @unlink($full_old);
        }

        // UPDATE with new evidence_file
        // 16 params: s s s s s i s s s s s i s i | i i
        $stmt = $conn->prepare("
            UPDATE incident_reports SET
                incident_datetime = ?,
                nature            = ?,
                attendance_detail = ?,
                offense_category  = ?,
                conduct_detail    = ?,
                minutes_value     = ?,
                dates_in_month    = ?,
                minutes_data      = ?,
                evidence          = ?,
                evidence_file     = ?,
                action_taken      = ?,
                suspension_days   = ?,
                recommendations   = ?,
                custom_html       = NULL,
                custom_html_at    = NULL,
                updated_by        = ?,
                updated_at        = NOW()
            WHERE id = ? AND employee_id = ?
        ");

        if (!$stmt) jsonExit(false, 'Prepare failed: ' . $conn->error);

        $stmt->bind_param(
    "sssssisssssisiii",
    $incident_datetime,   // s
    $nature,              // s
    $attendance_detail,   // s
    $offense_category,    // s
    $conduct_detail,      // s
    $minutes_value,       // i
    $dates_in_month,      // s
    $minutes_data,        // s
    $evidence,            // s
    $evidence_file,       // s
    $action_taken_final,  // s
    $suspension_days,     // i
    $recommendations,     // s
    $admin_session_id,    // i
    $report_id,           // i
    $employee_id          // i
);

    } else {
        // No new file → keep existing evidence_file unchanged
        // 15 params: s s s s s i s s s s i s i | i i
        $stmt = $conn->prepare("
            UPDATE incident_reports SET
                incident_datetime = ?,
                nature            = ?,
                attendance_detail = ?,
                offense_category  = ?,
                conduct_detail    = ?,
                minutes_value     = ?,
                dates_in_month    = ?,
                minutes_data      = ?,
                evidence          = ?,
                action_taken      = ?,
                suspension_days   = ?,
                recommendations   = ?,
                custom_html       = NULL,
                custom_html_at    = NULL,
                updated_by        = ?,
                updated_at        = NOW()
            WHERE id = ? AND employee_id = ?
        ");

        if (!$stmt) jsonExit(false, 'Prepare failed: ' . $conn->error);

        $stmt->bind_param(
    "sssssississiiii",   // ← 15 chars now ✅ (was "sssssississiii" — missing one 'i')
    $incident_datetime,
    $nature,
    $attendance_detail,
    $offense_category,
    $conduct_detail,
    $minutes_value,     // i
    $dates_in_month,
    $minutes_data,
    $evidence,
    $action_taken_final,
    $suspension_days,   // i
    $recommendations,
    $admin_session_id,  // i
    $report_id,         // i
    $employee_id        // i
);
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        jsonExit(false, 'Database update error: ' . $err);
    }
    $stmt->close();

    // ── Re-insert tardiness records (delete old, insert new) ──────────
    $del = $conn->prepare("DELETE FROM incident_report_tardiness WHERE incident_report_id = ?");
    $del->bind_param("i", $report_id);
    $del->execute();
    $del->close();

    if (!empty($minutes_data) && $minutes_data !== '[]') {
        $records = json_decode($minutes_data, true);
        if (is_array($records)) {
            $t_stmt = $conn->prepare("
                INSERT INTO incident_report_tardiness (incident_report_id, tardiness_date, minutes_late)
                VALUES (?, ?, ?)
            ");
            foreach ($records as $record) {
                $t_date = $record['date']    ?? null;
                $t_min  = (int)($record['minutes'] ?? 0);
                if ($t_date && $t_min > 0) {
                    $t_stmt->bind_param("isi", $report_id, $t_date, $t_min);
                    $t_stmt->execute();
                }
            }
            $t_stmt->close();
        }
    }

    jsonExit(true, 'Incident report updated successfully.', [
        'report_id' => $report_id,
        'pdf_url'   => 'incident_report_pdf_viewer.php?id=' . $report_id
    ]);
}

// ══════════════════════════════════════════════════════════════════════
//  INSERT new report
// ══════════════════════════════════════════════════════════════════════
$file_col = $evidence_file ?? '';

// 15 params: i s s | s s s | i s s | s s | s i s | i
$stmt = $conn->prepare("
    INSERT INTO incident_reports
        (employee_id, incident_datetime, nature,
         attendance_detail, offense_category, conduct_detail,
         minutes_value, dates_in_month, minutes_data,
         evidence, evidence_file,
         action_taken, suspension_days, recommendations,
         created_by, created_at)
    VALUES
        (?, ?, ?,
         ?, ?, ?,
         ?, ?, ?,
         ?, ?,
         ?, ?, ?,
         ?, NOW())
");

if (!$stmt) {
    jsonExit(false, 'Prepare failed: ' . $conn->error .
        ' — Ensure these columns exist: offense_category, evidence_file, suspension_days, minutes_data');
}

$stmt->bind_param(
    "isssssisssssisi",   // ← 15 chars, 15 variables ✅
    $employee_id,        // i
    $incident_datetime,  // s
    $nature,             // s
    $attendance_detail,  // s
    $offense_category,   // s
    $conduct_detail,     // s
    $minutes_value,      // i
    $dates_in_month,     // s
    $minutes_data,       // s
    $evidence,           // s
    $file_col,           // s
    $action_taken_final, // s
    $suspension_days,    // i
    $recommendations,    // s
    $admin_session_id    // i
);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    jsonExit(false, 'Database error: ' . $err);
}

$new_report_id = $conn->insert_id;
$stmt->close();

// ── Insert tardiness records ───────────────────────────────────────────
if (!empty($minutes_data) && $minutes_data !== '[]') {
    $records = json_decode($minutes_data, true);
    if (is_array($records)) {
        $t_stmt = $conn->prepare("
            INSERT INTO incident_report_tardiness (incident_report_id, tardiness_date, minutes_late)
            VALUES (?, ?, ?)
        ");
        foreach ($records as $record) {
            $t_date = $record['date']    ?? null;
            $t_min  = (int)($record['minutes'] ?? 0);
            if ($t_date && $t_min > 0) {
                $t_stmt->bind_param("isi", $new_report_id, $t_date, $t_min);
                $t_stmt->execute();
            }
        }
        $t_stmt->close();
    }
}

jsonExit(true, 'Incident Report Submitted Successfully', [
    'report_id' => $new_report_id,
    'pdf_url'   => 'incident_report_pdf_viewer.php?id=' . $new_report_id
]);
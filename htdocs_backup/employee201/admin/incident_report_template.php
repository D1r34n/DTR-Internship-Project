<?php
/**
 * Incident Report PDF Letter Template
 * Handles both rendering and editing of incident report letters
 */
require_once __DIR__ . '/../auth/session_check.php';
function generateLetterHTML($data) {
    // Extract data
    $employee = $data['employee'] ?? [];
    $adminName = $data['adminName'] ?? 'MANAGEMENT';
    $adminPosition = $data['adminPosition'] ?? 'Human Resources Department';
    $nature = $data['nature'] ?? '';
    $attendanceType = $data['attendanceType'] ?? '';
    $selectedDatesData = $data['selectedDatesData'] ?? [];
    $editedContent = $data['editedContent'] ?? [];
    
    // Get values with edited content priority
    $today = $editedContent['date'] ?? date('F d, Y');
    $employeeName = $editedContent['employeeName'] ?? ($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']);
    $employeePosition = $editedContent['employeePosition'] ?? $employee['position'];
    $employeeDepartment = $editedContent['employeeDepartment'] ?? $employee['department'];
    $signatureName = $editedContent['signatureName'] ?? $adminName;
    $signatureTitle = $editedContent['signatureTitle'] ?? $adminPosition;
    
    // Helper function
    $escapeHtml = function($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    };
    
    $incidentDate = $editedContent['incidentDate'] ?? '';
    $natureDisplayLabel = $editedContent['natureLabel'] ?? ($nature === 'attendance' ? 'Attendance and Punctuality' : 'Conduct and Behavior');
    $greetingName = $editedContent['greeting'] ?? explode(' ', $employeeName)[0];
    
    // Attendance/Conduct details
    $attendanceLabel = [
        'late' => 'Repeated late arrivals',
        'awol' => 'Absent without notice (AWOL)',
        'frequent_absences' => 'Frequent or unexplained absences',
        'early_leave' => 'Leaving work early without permission',
        'not_logging' => 'Not logging attendance properly'
    ][$attendanceType] ?? '';
    
    $conduct = $data['conduct'] ?? '';
    $evidence = $editedContent['evidence'] ?? $data['evidence'] ?? '';
    $actionTaken = $editedContent['actionTaken'] ?? $data['actionTaken'] ?? '';
    $recommendations = $editedContent['recommendations'] ?? $data['recommendations'] ?? '';
    
    // Build table if applicable
    $tardinessTable = '';
    if (!empty($selectedDatesData) && $nature === 'attendance' && $attendanceType === 'late') {
        $tardinessTable = '<p>The following tardiness records have been documented:</p>';
        $tardinessTable .= '<table class="letter-table" style="width:100%; border-collapse:collapse; margin:15px 0; font-size:13px;">';
        $tardinessTable .= '<thead><tr style="background:#667eea; color:white;">';
        $tardinessTable .= '<th style="border:1px solid #d0d7f5; padding:10px 12px; text-align:left;">Date</th>';
        $tardinessTable .= '<th style="border:1px solid #d0d7f5; padding:10px 12px; text-align:center;">Minutes Late</th>';
        $tardinessTable .= '</tr></thead><tbody>';
        
        foreach ($selectedDatesData as $item) {
            $dateObj = new DateTime($item['date']);
            $formattedDate = $dateObj->format('F d, Y');
            $tardinessTable .= '<tr>';
            $tardinessTable .= '<td style="border:1px solid #d0d7f5; padding:10px 12px;">' . $escapeHtml($formattedDate) . '</td>';
            $tardinessTable .= '<td style="border:1px solid #d0d7f5; padding:10px 12px; text-align:center;">' . (int)$item['minutes'] . ' minutes</td>';
            $tardinessTable .= '</tr>';
        }
        
        $tardinessTable .= '</tbody></table>';
    } elseif (!empty($selectedDatesData) && $nature === 'attendance' && $attendanceType !== 'late') {
        $datesList = array_map(function($item) {
            $dateObj = new DateTime($item['date']);
            return $dateObj->format('F d, Y');
        }, $selectedDatesData);
        $tardinessTable = '<p>Dates recorded: ' . implode(', ', $datesList) . '</p>';
    }
    
    // Paragraph content
    $para1 = $editedContent['para1'] ?? ($nature === 'attendance' 
        ? "This letter serves as a formal Incident Report concerning your attendance record on $incidentDate."
        : "This letter serves as a formal Incident Report regarding a conduct matter on $incidentDate.");
    
    $para2 = $editedContent['para2'] ?? ($nature === 'attendance'
        ? "Based on the attendance log, the following attendance issue has been documented: <strong>" . $escapeHtml($attendanceLabel) . "</strong>."
        : "The following conduct issue has been documented: <strong>" . $escapeHtml($conduct) . "</strong>.");
    
    $para3 = $editedContent['para3'] ?? ($nature === 'attendance'
        ? 'It is critical that you understand that habitual tardiness negatively affects team operations, productivity, and overall workplace efficiency. While we understand that unforeseen circumstances may occur, employees are expected to notify their immediate supervisor in advance whenever possible.'
        : 'This conduct must be corrected immediately. Continued violations may result in further disciplinary action up to and including termination of employment.');
    
    $para4 = $editedContent['para4'] ?? ($nature === 'attendance'
        ? 'You are hereby requested to submit a written explanation within twenty-four (24) hours upon receipt of this notice. Further violations of company attendance policies may lead to more serious disciplinary action in accordance with company rules and regulations.'
        : 'You are required to meet with the Human Resources Department within forty-eight (48) hours to discuss this matter.');
    
    $para5 = $editedContent['para5'] ?? ($nature === 'attendance'
        ? 'We trust that you will take the necessary corrective measures to improve your attendance and adhere strictly to company policies moving forward.'
        : '');
    
    $para6 = $editedContent['para6'] ?? 'Should you wish to discuss this matter, you may coordinate with the Human Resources Department.';
    $para7 = $editedContent['para7'] ?? 'Thank you for your prompt attention to this matter.';
    
    // Build conditional blocks (cannot use {( )} inside heredoc — use variables instead)
    $evidenceBlock      = $evidence
        ? '<p data-field="evidence"><strong>Evidence/Details:</strong> ' . $escapeHtml($evidence) . '</p>'
        : '';
    $actionTakenBlock   = $actionTaken
        ? '<p data-field="actionTaken"><strong>Immediate Action Taken:</strong> ' . $escapeHtml($actionTaken) . '</p>'
        : '';
    $recommendationsBlock = $recommendations
        ? '<p data-field="recommendations"><strong>Recommendations/Follow-up:</strong> ' . $escapeHtml($recommendations) . '</p>'
        : '';
    $para5Block         = $para5
        ? '<p data-field="para5">' . $para5 . '</p>'
        : '';

    // Generate HTML
    $html = <<<HTML
<div class="letter-document" style="font-family:'Calibri','Georgia',serif;font-size:13px;line-height:1.8;color:#333;">
    <div class="letter-date" style="margin-bottom:30px;">
        <p>Date: <span data-field="date">{$escapeHtml($today)}</span></p>
    </div>

    <div class="letter-recipient" style="margin-bottom:30px;line-height:1.8;">
        <div data-field="employeeName" style="font-weight:600;margin-bottom:3px;">{$escapeHtml($employeeName)}</div>
        <div data-field="employeePosition">{$escapeHtml($employeePosition)}</div>
        <div data-field="employeeDepartment">{$escapeHtml($employeeDepartment)}</div>
    </div>

    <div class="letter-subject" style="margin:30px 0;">
        <p><strong>Subject: Formal Incident Report – <span data-field="natureLabel">{$escapeHtml($natureDisplayLabel)}</span></strong></p>
    </div>

    <div class="letter-greeting" style="margin-bottom:20px;">
        <p>Dear <span data-field="greeting">{$escapeHtml($greetingName)}</span>,</p>
    </div>

    <div class="letter-body" style="text-align:justify;margin-bottom:25px;">
        <p data-field="para1">$para1</p>
        <p data-field="para2">$para2</p>
        $tardinessTable
        $evidenceBlock
        <p data-field="para3">$para3</p>
        <p data-field="para4">$para4</p>
        $actionTakenBlock
        $recommendationsBlock
        $para5Block
        <p data-field="para6">$para6</p>
        <p data-field="para7">$para7</p>
    </div>

    <div class="letter-closing" style="margin-top:40px;">
        <p>Sincerely,</p>
        <div class="letter-signature" style="display:inline-block;margin-top:20px;">
            <div class="letter-signature-line" style="width:200px;border-top:1px solid #000;margin-bottom:8px;"></div>
            <div data-field="signatureTitle" style="font-size:13px;color:#333;margin:3px 0;">{$escapeHtml($signatureTitle)}</div>
            <div data-field="signatureName" style="font-size:13px;color:#333;margin:3px 0;">{$escapeHtml($signatureName)}</div>
        </div>
    </div>

    <div class="acknowledgment-section" style="margin-top:50px; padding:20px; border:1px solid #000; font-size:12px; line-height:1.8;">
        <p><strong>EMPLOYEE ACKNOWLEDGMENT:</strong></p>
        <p>I acknowledge receipt of this Incident Report.</p>
        <p><strong>Employee Signature:</strong> <span class="signature-line-ack" style="display:inline-block;width:180px;border-top:1px solid #000;margin-top:8px;"></span></p>
        <p><strong>Date Received:</strong> <span class="signature-line-ack" style="display:inline-block;width:180px;border-top:1px solid #000;margin-top:8px;"></span></p>
    </div>
</div>
HTML;

    return $html;
}

/**
 * Generate Full HTML Page for Editing
 */
function generateEditablePage($data) {
    $letterHTML = generateLetterHTML($data);
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Incident Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
            line-height: 1.6;
        }

        .pdf-wrapper {
            max-width: 850px;
            margin: 0 auto;
        }

        .action-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-pdf-download {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-pdf-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-print {
            background: #28a745;
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.6);
        }

        .btn-close {
            background: #6c757d;
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.4);
        }

        .btn-close:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.6);
        }

        .pdf-container {
            background: white;
            padding: 50px 60px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            line-height: 1.8;
            color: #333;
        }

        .pdf-container[contenteditable="true"] {
            outline: 2px dashed #667eea;
        }

        .pdf-container[contenteditable="true"]:focus {
            outline: 2px solid #667eea;
            background: #f8f9ff;
        }

        /* Edit Mode Indicator */
        .edit-mode-warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #92400e;
        }

        .edit-hint-badge {
            background: #fbbf24;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }

        .changes-counter {
            background: #f97316;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            margin-left: auto;
        }

        .undo-btn {
            background: #f97316;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 10px;
            font-size: 12px;
        }

        .undo-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .pdf-wrapper {
                max-width: 100%;
            }

            .action-bar,
            .edit-mode-warning {
                display: none;
            }

            .pdf-container {
                box-shadow: none;
                padding: 40px 50px;
                border-radius: 0;
            }

            @page {
                margin: 20mm;
                size: A4;
            }
        }

        .incident-details {
            background: #f9f9f9;
            padding: 15px;
            margin: 20px 0;
            border-left: 3px solid #667eea;
            font-size: 13px;
        }

        .incident-details p {
            margin: 8px 0;
        }

        .incident-details strong {
            color: #333;
        }
    </style>
</head>
<body>

<div class="pdf-wrapper">
    <!-- Action Bar -->
    <div class="action-bar">
        <button class="btn-action btn-pdf-download" onclick="downloadPDF()">
            📥 Download as PDF
        </button>
        <button class="btn-action btn-print" onclick="window.print()">
            🖨️ Print
        </button>
        <button class="btn-action btn-close" onclick="closeWindow()">
            ✕ Close
        </button>
    </div>

    <!-- Edit Mode Warning -->
    <div class="edit-mode-warning">
        <span class="edit-hint-badge">✏️ Click anywhere to edit</span>
        <span style="opacity:0.6; font-size:11px;">
            Bold: <kbd style="background:#334155;border:1px solid #475569;border-radius:3px;padding:1px 5px;color:#e2e8f0;">Ctrl+B</kbd> &nbsp; 
            Italic: <kbd style="background:#334155;border:1px solid #475569;border-radius:3px;padding:1px 5px;color:#e2e8f0;">Ctrl+I</kbd>
        </span>
        <span class="changes-counter" id="changesCounter">0 edits</span>
        <button class="undo-btn" id="undoBtn" onclick="undoLastEdit()" disabled>↩ Undo</button>
    </div>

    <!-- PDF Container (Editable) -->
    <div class="pdf-container" id="pdfContent" contenteditable="true">
        $letterHTML
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
let docHistory = [];
let totalEdits = 0;

function recordDocSnapshot() {
    const editor = document.getElementById('pdfContent');
    if (!editor) return;
    docHistory.push(editor.innerHTML);
    if (docHistory.length > 50) docHistory.shift();
    totalEdits++;
    updateUndoUI();
}

function undoLastEdit() {
    if (docHistory.length <= 1) return;
    docHistory.pop();
    const previous = docHistory[docHistory.length - 1];
    const editor = document.getElementById('pdfContent');
    if (editor) {
        editor.innerHTML = previous;
    }
    totalEdits = Math.max(0, totalEdits - 1);
    updateUndoUI();
}

function updateUndoUI() {
    const undoBtn = document.getElementById('undoBtn');
    const counter = document.getElementById('changesCounter');
    if (undoBtn) undoBtn.disabled = docHistory.length <= 1;
    if (counter) {
        counter.textContent = totalEdits + (totalEdits === 1 ? ' edit' : ' edits');
    }
}

// Track edits with debounce
let inputDebounce = null;
document.getElementById('pdfContent').addEventListener('input', function() {
    clearTimeout(inputDebounce);
    inputDebounce = setTimeout(() => {
        recordDocSnapshot();
    }, 600);
});

// Undo with Ctrl+Z
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'z') {
        e.preventDefault();
        undoLastEdit();
    }
});

function downloadPDF() {
    const element = document.getElementById('pdfContent');
    const filename = 'Incident_Report_' + new Date().toISOString().slice(0, 10) + '.pdf';
    
    const options = {
        margin: [20, 20, 20, 20],
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
    };

    html2pdf().set(options).from(element).save();
}

function closeWindow() {
    if (window.frameElement) {
        parent.location.reload();
    } else {
        window.close();
    }
}

// Initialize
const editor = document.getElementById('pdfContent');
if (editor) {
    docHistory.push(editor.innerHTML);
    updateUndoUI();
}
</script>

</body>
</html>
HTML;

    return $html;
}
?>
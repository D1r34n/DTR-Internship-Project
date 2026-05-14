<?php
/**
 * Shared Incident Letter Template
 * Used by both incident_report.php and incident_report_pdf_viewer.php
 */

function escapeHtml($text) {
    $map = [
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;',
        '"' => '&quot;',
        "'" => '&#039;'
    ];
    return str_replace(array_keys($map), array_values($map), $text);
}

function generateIncidentLetter($params) {
    extract($params);
    
    // Build tardiness table if applicable
    $tardinessTable = '';
    if (!empty($selectedDatesData) && $nature === 'attendance' && $attendanceType === 'late') {
        $tardinessTable = '
            <p>The following tardiness records have been documented:</p>
            <table class="letter-table" style="width:100%;border-collapse:collapse;margin:15px 0;font-size:13px;">
                <thead>
                    <tr>
                        <th style="border:1px solid #d0d7f5;padding:10px 12px;text-align:left;background:#667eea;color:white;">Date</th>
                        <th style="border:1px solid #d0d7f5;padding:10px 12px;text-align:center;background:#667eea;color:white;">Minutes Late</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($selectedDatesData as $item) {
            $dateObj = new DateTime($item['date']);
            $formattedDate = $dateObj->format('F d, Y');
            $tardinessTable .= "
                    <tr>
                        <td style=\"border:1px solid #d0d7f5;padding:10px 12px;\">{$formattedDate}</td>
                        <td style=\"border:1px solid #d0d7f5;padding:10px 12px;text-align:center;\">{$item['minutes']} minutes</td>
                    </tr>";
        }
        
        $tardinessTable .= '
                </tbody>
            </table>';
    } elseif (!empty($selectedDatesData) && $nature === 'attendance' && $attendanceType !== 'late') {
        $datesList = implode(', ', array_map(function($item) {
            $dateObj = new DateTime($item['date']);
            return $dateObj->format('F d, Y');
        }, $selectedDatesData));
        $tardinessTable = "<p>Dates recorded: {$datesList}</p>";
    }

    $letterHTML = "
        <div class=\"letter-document\" style=\"font-family:'Calibri','Georgia',serif;font-size:13px;line-height:1.8;color:#333;\">
            <!-- Company Header -->
            <div style=\"margin-bottom:30px;font-size:13px;line-height:1.7;border-bottom:1px solid #000;padding-bottom:15px;\">
                <p style=\"margin:4px 0;\"><strong>TRES MARIAS</strong></p>
                <p style=\"margin:4px 0;\">Human Resources Department</p>
                <p style=\"margin:4px 0;\">1172-1180, 1172 President Quirino Ave Ext, Paco, Manila, Metro Manila</p>
                <p style=\"margin:4px 0;\">Manila City, Metro Manila, 1004</p>
                <p style=\"margin:4px 0;\">(02) 8555 2300</p>
            </div>

            <!-- Date -->
            <div style=\"margin-bottom:25px;font-size:13px;\">
                <p>Date: <span data-field=\"date\">" . escapeHtml($date) . "</span></p>
            </div>

            <!-- Recipient -->
            <div style=\"margin-bottom:25px;font-size:13px;line-height:1.8;\">
                <div data-field=\"employeeName\" style=\"font-weight:600;margin-bottom:3px;\">" . escapeHtml($employeeName) . "</div>
                <div data-field=\"employeePosition\">" . escapeHtml($employeePosition) . "</div>
                <div data-field=\"employeeDepartment\">" . escapeHtml($employeeDepartment) . "</div>
                <div><strong>TRES MARIAS</strong></div>
            </div>

            <!-- Subject Line -->
            <div style=\"margin:30px 0;font-size:13px;\">
                <p><strong>Subject: Formal Incident Report – <span data-field=\"natureLabel\">" . escapeHtml($natureLabel) . "</span></strong></p>
            </div>

            <!-- Salutation -->
            <div style=\"margin-bottom:20px;\">
                <p>Dear <span data-field=\"greeting\">" . escapeHtml($greeting) . "</span>,</p>
            </div>

            <!-- Letter Body -->
            <div style=\"font-size:13px;line-height:1.8;text-align:justify;margin-bottom:25px;\">
                <p data-field=\"para1\">" . $para1 . "</p>
                <p data-field=\"para2\">" . $para2 . "</p>
                " . $tardinessTable . "
                " . (!empty($evidence) ? "<p data-field=\"evidence\"><strong>Evidence/Details:</strong> " . escapeHtml($evidence) . "</p>" : "") . "
                <p data-field=\"para3\">" . $para3 . "</p>
                <p data-field=\"para4\">" . $para4 . "</p>
                " . (!empty($actionTaken) ? "<p data-field=\"actionTaken\"><strong>Immediate Action Taken:</strong> " . escapeHtml($actionTaken) . "</p>" : "") . "
                " . (!empty($recommendations) ? "<p data-field=\"recommendations\"><strong>Recommendations/Follow-up:</strong> " . escapeHtml($recommendations) . "</p>" : "") . "
                " . (!empty($para5) ? "<p data-field=\"para5\">" . $para5 . "</p>" : "") . "
                <p data-field=\"para6\">" . $para6 . "</p>
                <p data-field=\"para7\">" . $para7 . "</p>
            </div>

            <!-- Closing -->
            <div style=\"margin-top:40px;font-size:13px;\">
                <p>Sincerely,</p>
                <div style=\"display:inline-block;margin-top:20px;\">
                    <div style=\"width:200px;border-top:1px solid #000;margin-bottom:8px;\"></div>
                    <div data-field=\"signatureTitle\" style=\"font-size:13px;color:#333;margin:3px 0;\">" . escapeHtml($signatureTitle) . "</div>
                    <div data-field=\"signatureName\" style=\"font-size:13px;color:#333;margin:3px 0;\">" . escapeHtml($signatureName) . "</div>
                </div>
            </div>

            <!-- Employee Acknowledgment Section -->
            <div style=\"margin-top:50px;padding:20px;border:1px solid #000;font-size:12px;line-height:1.8;\">
                <p style=\"margin:8px 0;font-weight:600;\">EMPLOYEE ACKNOWLEDGMENT:</p>
                <p style=\"margin:8px 0;\">I acknowledge receipt of this Incident Report.</p>
                <p style=\"margin:8px 0;\"><strong>Employee Signature:</strong> <span style=\"display:inline-block;width:180px;border-top:1px solid #000;margin-top:8px;\"></span></p>
                <p style=\"margin:8px 0;\"><strong>Date Received:</strong> <span style=\"display:inline-block;width:180px;border-top:1px solid #000;margin-top:8px;\"></span></p>
            </div>
        </div>
    ";

    return $letterHTML;
}
?>
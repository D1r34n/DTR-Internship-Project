let editedContent = {
    date: '',
    employeeName: '',
    employeePosition: '',
    employeeDepartment: '',
    natureLabel: '',
    attendanceLabel: '',
    evidence: '',
    actionTaken: '',
    recommendations: '',
    para1: '', para2: '', para3: '', para4: '', para5: '', para6: '', para7: '',
    greeting: '', signatureTitle: '', signatureName: ''
};

// Full document HTML snapshot for undo
let docHistory = [];
let autoSaveTimer = null;
let totalEdits = 0;

function recordDocSnapshot() {
    const editor = document.getElementById('letterDocEditor');
    if (!editor) return;
    docHistory.push(editor.innerHTML);
    if (docHistory.length > 50) docHistory.shift(); // cap at 50 undos
    totalEdits++;
    updateUndoUI();
    showAutoSaveBadge();
}

function undoLastEdit() {
    if (docHistory.length <= 1) return;
    docHistory.pop(); // discard current
    const previous = docHistory[docHistory.length - 1];
    const editor = document.getElementById('letterDocEditor');
    if (editor) {
        editor.innerHTML = previous;
        // Sync editedContent from restored HTML
        syncEditedContentFromEditor();
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

// Sync plain-text fields back into editedContent from the live editor DOM
function syncEditedContentFromEditor() {
    const editor = document.getElementById('letterDocEditor');
    if (!editor) return;
    const fields = editor.querySelectorAll('[data-field]');
    fields.forEach(el => {
        const key = el.dataset.field;
        if (key) editedContent[key] = el.innerText.trim();
    });
}

// Keyboard shortcuts: Ctrl+Z triggers our undo while modal is open
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'z') {
        const modal = document.getElementById('pdfPreviewModal');
        if (modal && modal.style.display === 'flex') {
            e.preventDefault();
            undoLastEdit();
        }
    }
});

const attendanceLabels = {
    late: {
        minutesTitle: 'Minutes of Late',
        minutesPlaceholder: 'Enter total minutes late',
        datesTitle: 'Dates of Late in a Month',
        datesPlaceholder: 'Example: 3, 7, 12, 18',
        showMinutes: true
    },
    awol: {
        minutesTitle: '',
        minutesPlaceholder: '',
        datesTitle: 'Dates of AWOL in a Month',
        datesPlaceholder: 'Example: 3, 7, 12, 18',
        showMinutes: false
    },
    frequent_absences: {
        minutesTitle: '',
        minutesPlaceholder: '',
        datesTitle: 'Dates of Absence in a Month',
        datesPlaceholder: 'Example: 3, 7, 12, 18',
        showMinutes: false
    },
    early_leave: {
        minutesTitle: '',
        minutesPlaceholder: '',
        datesTitle: 'Dates of Early Leave in a Month',
        datesPlaceholder: 'Example: 3, 7, 12, 18',
        showMinutes: false
    },
    not_logging: {
        minutesTitle: '',
        minutesPlaceholder: '',
        datesTitle: 'Dates Not Logged in a Month',
        datesPlaceholder: 'Example: 3, 7, 12, 18',
        showMinutes: false
    }
};

let selectedDatesData = [];

function toggleCategorySections() {
    const category = document.getElementById('incidentCategory').value;
    document.getElementById('attendanceOptions').style.display = category === 'attendance' ? 'block' : 'none';
    document.getElementById('conductOptions').style.display = category === 'conduct' ? 'block' : 'none';

    if (category !== 'attendance') {
        document.getElementById('attendanceDetails').style.display = 'none';
        document.getElementById('singleMinutesField').value = '';
        selectedDatesData = [];
        document.getElementById('datePickerInput').value = '';
        document.getElementById('minutesInputField').value = '';
        document.getElementById('datesField').value = '';
        document.getElementById('minutesData').value = '';
        updateDatesList();
    }
}

function setDatePickerMaxDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const maxDate = `${year}-${month}-${day}`;
    
    const datePickerInput = document.getElementById('datePickerInput');
    if (datePickerInput) {
        datePickerInput.max = maxDate;
    }
}

function updateAttendanceDetails() {
    const category = document.getElementById('incidentCategory').value;
    const type = document.getElementById('attendanceType').value;

    if (category !== 'attendance' || !type) {
        document.getElementById('attendanceDetails').style.display = 'none';
        return;
    }

    const config = attendanceLabels[type];
    if (!config) {
        document.getElementById('attendanceDetails').style.display = 'none';
        return;
    }

    selectedDatesData = [];
    document.getElementById('datePickerInput').value = '';
    document.getElementById('minutesInputField').value = '';
    document.getElementById('datesField').value = '';
    document.getElementById('minutesData').value = '';
    updateDatesList();

    const minutesBox = document.getElementById('minutesBox');
    const minutesInputField = document.getElementById('minutesInputField');

    if (config.showMinutes) {
        minutesBox.style.display = 'block';
        minutesInputField.style.display = 'block';
    } else {
        minutesBox.style.display = 'none';
        minutesInputField.style.display = 'none';
        document.getElementById('singleMinutesField').value = '';
    }

    document.getElementById('datesTitle').textContent = config.datesTitle;
    document.getElementById('attendanceDetails').style.display = 'block';
    setDatePickerMaxDate();
}

function addDate() {
    const dateInput = document.getElementById('datePickerInput');
    const minutesInput = document.getElementById('minutesInputField');
    const dateValue = dateInput.value;
    const minutesValue = minutesInput.value;
    const attendanceType = document.getElementById('attendanceType').value;
    const config = attendanceLabels[attendanceType];

    if (!dateValue) {
        alert('Please select a date.');
        return;
    }

    const selectedDate = new Date(dateValue + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate > today) {
        alert('Future dates are not allowed. Please select today or an earlier date.');
        return;
    }

    if (config.showMinutes) {
        if (!minutesValue || minutesValue <= 0) {
            alert('Please enter minutes late.');
            return;
        }
    }

    if (selectedDatesData.some(d => d.date === dateValue)) {
        alert('This date has already been added.');
        return;
    }

    selectedDatesData.push({
        date: dateValue,
        minutes: config.showMinutes ? parseInt(minutesValue) : 0
    });
    
    selectedDatesData.sort((a, b) => new Date(a.date) - new Date(b.date));

    dateInput.value = '';
    minutesInput.value = '';

    updateDatesList();
    updatePreviewDates();
}

function removeDate(dateValue) {
    selectedDatesData = selectedDatesData.filter(d => d.date !== dateValue);
    updateDatesList();
    updatePreviewDates();
}

function updateDatesList() {
    const container = document.getElementById('datesListContainer');
    const hiddenField = document.getElementById('datesField');
    const minutesField = document.getElementById('minutesData');

    container.innerHTML = '';

    if (selectedDatesData.length === 0) {
        hiddenField.value = '';
        minutesField.value = '';
        return;
    }

    selectedDatesData.forEach(item => {
        const dateObj = new Date(item.date + 'T00:00:00');
        const day = dateObj.getDate();
        const month = dateObj.toLocaleString('en-US', { month: 'short' });
        const year = dateObj.getFullYear();
        const displayDate = `${month} ${day}, ${year}`;

        const dateItem = document.createElement('div');
        dateItem.className = 'date-item';
        
        const attendanceType = document.getElementById('attendanceType').value;
        const config = attendanceLabels[attendanceType];
        
        if (config.showMinutes) {
            dateItem.innerHTML = `
                <div class="date-item-details">
                    <span class="date-item-value">${displayDate}</span>
                    <span class="date-item-minutes">${item.minutes} min</span>
                </div>
                <button type="button" class="date-item-remove" onclick="removeDate('${item.date}')" title="Remove date">×</button>
            `;
        } else {
            dateItem.innerHTML = `
                <div class="date-item-details">
                    <span class="date-item-value">${displayDate}</span>
                </div>
                <button type="button" class="date-item-remove" onclick="removeDate('${item.date}')" title="Remove date">×</button>
            `;
        }
        container.appendChild(dateItem);
    });

    const dayNumbers = selectedDatesData.map(d => new Date(d.date + 'T00:00:00').getDate()).join(', ');
    hiddenField.value = dayNumbers;
    minutesField.value = JSON.stringify(selectedDatesData);
}

function updatePreviewDates() {
    if (selectedDatesData.length > 0) {
        document.getElementById('previewDatesFieldContainer').style.display = 'flex';
        const datesList = document.getElementById('previewDatesList');
        datesList.innerHTML = '';
        selectedDatesData.forEach(item => {
            const dateObj = new Date(item.date + 'T00:00:00');
            const day = dateObj.getDate();
            const month = dateObj.toLocaleString('en-US', { month: 'short' });
            const year = dateObj.getFullYear();
            const badge = document.createElement('span');
            badge.className = 'preview-date-badge';
            
            const attendanceType = document.getElementById('attendanceType').value;
            const config = attendanceLabels[attendanceType];
            
            if (config.showMinutes) {
                badge.textContent = `${month} ${day}, ${year} - ${item.minutes} min`;
            } else {
                badge.textContent = `${month} ${day}, ${year}`;
            }
            datesList.appendChild(badge);
        });
    } else {
        document.getElementById('previewDatesFieldContainer').style.display = 'none';
    }
}

function updatePreviewAll() {
    const dateTime = document.getElementById('incident_datetime').value;
    if (dateTime) {
        const date = new Date(dateTime);
        const formatted = date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        });
        document.getElementById('previewDateTime').textContent = formatted;
    } else {
        document.getElementById('previewDateTime').textContent = '-';
    }

    const nature = document.getElementById('incidentCategory').value;
    const natureLabel = {
        'attendance': 'Attendance and Punctuality',
        'conduct': 'Conduct and Behavior'
    }[nature] || '-';
    document.getElementById('previewNature').textContent = natureLabel;

    const attendanceType = document.getElementById('attendanceType').value;
    const attendanceLabel = {
        'late': 'Repeated late arrivals',
        'awol': 'Absent without notice (AWOL)',
        'frequent_absences': 'Frequent or unexplained absences',
        'early_leave': 'Leaving work early without permission',
        'not_logging': 'Not logging attendance properly'
    }[attendanceType] || '-';

    if (nature === 'attendance' && attendanceType) {
        document.getElementById('previewAttendanceFieldContainer').style.display = 'flex';
        document.getElementById('previewAttendance').textContent = attendanceLabel;
    } else {
        document.getElementById('previewAttendanceFieldContainer').style.display = 'none';
    }

    const conduct = document.querySelector('select[name="conduct_detail"]').value;
    if (nature === 'conduct' && conduct) {
        document.getElementById('previewConductFieldContainer').style.display = 'flex';
        document.getElementById('previewConduct').textContent = conduct;
    } else {
        document.getElementById('previewConductFieldContainer').style.display = 'none';
    }

    const evidence = document.querySelector('textarea[name="evidence"]').value || '-';
    document.getElementById('previewEvidence').textContent = evidence;
    document.getElementById('previewEvidence').className = evidence === '-' ? 'preview-value preview-textarea empty' : 'preview-value preview-textarea';

    const actionSelect = document.getElementById('actionTakenSelect');
    const actionOther  = document.getElementById('actionTakenOther');
    let action = '-';
    if (actionSelect && actionSelect.value) {
        action = actionSelect.value === 'Other' && actionOther && actionOther.value.trim()
            ? actionOther.value.trim()
            : actionSelect.value;
    }
    document.getElementById('previewAction').textContent = action;
    document.getElementById('previewAction').className = action === '-' ? 'preview-value preview-textarea empty' : 'preview-value preview-textarea';

    const recommendations = document.querySelector('textarea[name="recommendations"]').value || '-';
    document.getElementById('previewRecommendations').textContent = recommendations;
    document.getElementById('previewRecommendations').className = recommendations === '-' ? 'preview-value preview-textarea empty' : 'preview-value preview-textarea';
}

function setupDocumentEditor() {
    const editor = document.getElementById('letterDocEditor');
    if (!editor) return;

    // Take initial snapshot
    if (docHistory.length === 0) {
        docHistory.push(editor.innerHTML);
    }

    let inputDebounce = null;

    editor.addEventListener('input', function() {
        // Debounce snapshot recording (record after 600ms of no typing)
        clearTimeout(inputDebounce);
        inputDebounce = setTimeout(() => {
            recordDocSnapshot();
            syncEditedContentFromEditor();
        }, 600);
    });

    // Also sync on blur (when user clicks away from editor)
    editor.addEventListener('blur', function() {
        clearTimeout(inputDebounce);
        recordDocSnapshot();
        syncEditedContentFromEditor();
    }, true);

    // Format toolbar actions
    document.querySelectorAll('.toolbar-btn').forEach(btn => {
        btn.addEventListener('mousedown', function(e) {
            e.preventDefault(); // don't lose focus
            const cmd = this.dataset.cmd;
            const val = this.dataset.val || null;
            document.execCommand(cmd, false, val);
            editor.focus();
        });
    });
}

// No longer used as before — kept for compatibility
function setupEditableFields() { setupDocumentEditor(); }
function makeFieldEditable() {}
function updateLetterPreviewWithEdits() {
    // Re-render only if modal is open and editor exists
    const editor = document.getElementById('letterDocEditor');
    if (editor) {
        syncEditedContentFromEditor();
    }
}

function viewPDFPreview() {
    const modal = document.getElementById('pdfPreviewModal');
    const modalBody = document.getElementById('pdfModalBody');
    const warning = document.getElementById('editModeWarning');

    if (Object.values(editedContent).every(v => v === '')) {
        docHistory = [];
        totalEdits = 0;
        updateUndoUI();
    }

    // Render the editable document
    const letterHTML = generateLetterHTML();
    modalBody.innerHTML = `
        <div class="letter-editor-wrap">
            <div id="letterDocEditor"
                 class="letter-doc-editable letter-document"
                 contenteditable="true"
                 spellcheck="true"
                 style="padding: 50px; background:white; min-height:500px; outline:none;">
                ${letterHTML}
            </div>
        </div>
    `;

    warning.classList.add('active');
    modal.style.display = 'flex';

    setupDocumentEditor();
}

function closePDFModal() {
    document.getElementById('pdfPreviewModal').style.display = 'none';
    document.getElementById('editModeWarning').classList.remove('active');
}

function printPDFPreview() {
    const editor = document.getElementById('letterDocEditor');
    const bodyHTML = editor ? editor.innerHTML : generateLetterHTML();

    const printWindow = window.open('', '', 'height=900,width=1000');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                /* This block removes the Date/Time/Title from the 2nd image */
                @page { 
                    margin: 0; 
                }
                body { 
                    font-family: 'Calibri', serif; 
                    margin: 1.5cm; /* Adds clean margin back to content */
                    padding: 0;
                }
                /* Hide the cursor in the print output */
                * { caret-color: transparent !important; }
                
                .letter-document { max-width: 850px; margin: 0 auto; }
                p { margin: 10px 0; line-height: 1.8; font-size: 13px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
                th, td { border: 1px solid #d0d7f5; padding: 10px 12px; text-align: left; }
                thead { background: #667eea !important; -webkit-print-color-adjust: exact; }
                th { color: #ffffff !important; }
                .letter-signature-line { width:200px; border-top:1px solid #000; margin-top:20px; }
            </style>
        </head>
        <body>
            ${bodyHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    
    // Give the browser a moment to apply styles before opening the dialog
    setTimeout(() => {
        printWindow.print();
    }, 500);
}

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

function generateLetterHTML() {
    const adminName = document.getElementById('adminName').value;
    const adminPosition = document.getElementById('adminPosition').value;

    const employee = {
        code:       document.getElementById('employeeCode')       ? document.getElementById('employeeCode').value       : '',
        name:       document.getElementById('employeeName')       ? document.getElementById('employeeName').value       : '',
        position:   document.getElementById('employeePosition')   ? document.getElementById('employeePosition').value   : '',
        department: document.getElementById('employeeDepartment') ? document.getElementById('employeeDepartment').value : ''
    };

    const dateTime = document.getElementById('incident_datetime').value;
    const incidentDate = dateTime ? new Date(dateTime).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
    
    const nature = document.getElementById('incidentCategory').value;
    const natureLabel = nature === 'attendance' ? 'Attendance and Punctuality' : 'Conduct and Behavior';
    
    const attendanceType = document.getElementById('attendanceType').value;
    const attendanceLabel = {
        'late': 'Repeated late arrivals',
        'awol': 'Absent without notice (AWOL)',
        'frequent_absences': 'Frequent or unexplained absences',
        'early_leave': 'Leaving work early without permission',
        'not_logging': 'Not logging attendance properly'
    }[attendanceType] || '';

    const conduct = document.querySelector('select[name="conduct_detail"]').value;
    
    const evidence = editedContent.evidence !== '' ? editedContent.evidence : document.querySelector('textarea[name="evidence"]').value;
    const _actionSel   = document.getElementById('actionTakenSelect');
    const _actionOther = document.getElementById('actionTakenOther');
    let _actionRaw = '';
    if (_actionSel && _actionSel.value) {
        _actionRaw = _actionSel.value === 'Other' && _actionOther && _actionOther.value.trim()
            ? _actionOther.value.trim()
            : _actionSel.value;
    }
    const actionTaken = editedContent.actionTaken !== '' ? editedContent.actionTaken : _actionRaw;
    const recommendations = editedContent.recommendations !== '' ? editedContent.recommendations : document.querySelector('textarea[name="recommendations"]').value;

    const today = editedContent.date || new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    const employeeName = editedContent.employeeName || employee.name;
    const employeePosition = editedContent.employeePosition || employee.position;
    const employeeDepartment = editedContent.employeeDepartment || employee.department;
    const signatureName = editedContent.signatureName || adminName;
    const signatureTitle = editedContent.signatureTitle || adminPosition;
    const natureDisplayLabel = editedContent.natureLabel || natureLabel;
    const greetingName = editedContent.greeting || employeeName.split(' ')[0];

    let tardinessTable = '';
    if (selectedDatesData.length > 0 && nature === 'attendance' && attendanceType === 'late') {
        tardinessTable = `
            <p>The following tardiness records have been documented:</p>
            <table class="letter-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th style="text-align: center;">Minutes Late</th>
                    </tr>
                </thead>
                <tbody>
                    ${selectedDatesData.map(item => `
                        <tr>
                            <td>${new Date(item.date + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                            <td style="text-align: center;">${item.minutes} minutes</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else if (selectedDatesData.length > 0 && nature === 'attendance' && attendanceType !== 'late') {
        const datesList = selectedDatesData.map(item => 
            new Date(item.date + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
        ).join(', ');
        tardinessTable = `<p>Dates recorded: ${datesList}</p>`;
    }

    const para1 = editedContent.para1 || (nature === 'attendance' 
        ? `This letter serves as a formal Incident Report concerning your attendance record on ${incidentDate}.`
        : `This letter serves as a formal Incident Report regarding a conduct matter on ${incidentDate}.`);
    
    const para2 = editedContent.para2 || (nature === 'attendance'
        ? `Based on the attendance log, the following attendance issue has been documented: <strong>${escapeHtml(attendanceLabel)}</strong>.`
        : `The following conduct issue has been documented: <strong>${escapeHtml(conduct)}</strong>.`);
    
    const para3 = editedContent.para3 || (nature === 'attendance'
        ? `It is critical that you understand that habitual tardiness negatively affects team operations, productivity, and overall workplace efficiency. While we understand that unforeseen circumstances may occur, employees are expected to notify their immediate supervisor in advance whenever possible.`
        : `This conduct must be corrected immediately. Continued violations may result in further disciplinary action up to and including termination of employment.`);
    
    const para4 = editedContent.para4 || (nature === 'attendance'
        ? `You are hereby requested to submit a written explanation within twenty-four (24) hours upon receipt of this notice. Further violations of company attendance policies may lead to more serious disciplinary action in accordance with company rules and regulations.`
        : `You are required to meet with the Human Resources Department within forty-eight (48) hours to discuss this matter.`);
    
    const para5 = editedContent.para5 || (nature === 'attendance'
        ? `We trust that you will take the necessary corrective measures to improve your attendance and adhere strictly to company policies moving forward.`
        : '');
    
    const para6 = editedContent.para6 || 'Should you wish to discuss this matter, you may coordinate with the Human Resources Department.';
    const para7 = editedContent.para7 || 'Thank you for your prompt attention to this matter.';

    const letterContent = `
        <div class="letter-document" style="font-family:'Calibri','Georgia',serif;font-size:13px;line-height:1.8;color:#333;">
            <div class="letter-date" style="margin-bottom:30px;">
                <p>Date: <span data-field="date">${escapeHtml(today)}</span></p>
            </div>

            <div class="letter-recipient" style="margin-bottom:30px;line-height:1.8;">
                <div data-field="employeeName" style="font-weight:600;margin-bottom:3px;">${escapeHtml(employeeName)}</div>
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
                ${evidence ? `<p data-field="evidence"><strong>Evidence/Details:</strong> ${escapeHtml(evidence)}</p>` : ''}
                <p data-field="para3">${para3}</p>
                <p data-field="para4">${para4}</p>
                ${actionTaken ? `<p data-field="actionTaken"><strong>Immediate Action Taken:</strong> ${escapeHtml(actionTaken)}</p>` : ''}
                ${recommendations ? `<p data-field="recommendations"><strong>Recommendations/Follow-up:</strong> ${escapeHtml(recommendations)}</p>` : ''}
                ${para5 ? `<p data-field="para5">${para5}</p>` : ''}
                <p data-field="para6">${para6}</p>
                <p data-field="para7">${para7}</p>
            </div>

            <div class="letter-closing" style="margin-top:40px;">
                <p>Sincerely,</p>
                <div class="letter-signature" style="display:inline-block;margin-top:20px;">
                    <div class="letter-signature-line" style="width:200px;border-top:1px solid #000;margin-bottom:8px;"></div>
                    <div data-field="signatureTitle" style="font-size:13px;color:#333;margin:3px 0;">${escapeHtml(signatureTitle)}</div>
                    <div data-field="signatureName" style="font-size:13px;color:#333;margin:3px 0;">${escapeHtml(signatureName)}</div>
                </div>
            </div>
        </div>
    `;

    return letterContent;
}

// Event Listeners
document.getElementById('incidentCategory').addEventListener('change', function () {
    document.getElementById('incident_datetime').value = '';
    document.getElementById('attendanceType').value = '';
    document.getElementById('singleMinutesField').value = '';
    document.getElementById('datePickerInput').value = '';
    document.getElementById('minutesInputField').value = '';
    document.getElementById('datesListContainer').innerHTML = '';
    document.getElementById('datesField').value = '';
    document.getElementById('minutesData').value = '';
    document.querySelector('select[name="conduct_detail"]').value = '';
    
    toggleCategorySections();
    updateAttendanceDetails();
    updatePreviewAll();
});

document.getElementById('attendanceType').addEventListener('change', function () {
    updateAttendanceDetails();
    updatePreviewAll();
});

document.getElementById('incident_datetime').addEventListener('change', updatePreviewAll);
document.querySelector('select[name="conduct_detail"]').addEventListener('change', updatePreviewAll);

document.querySelector('textarea[name="evidence"]').addEventListener('input', updatePreviewAll);
const actionTakenSelectEl = document.getElementById('actionTakenSelect');
const actionTakenOtherEl  = document.getElementById('actionTakenOther');
if (actionTakenSelectEl) actionTakenSelectEl.addEventListener('change', updatePreviewAll);
if (actionTakenOtherEl)  actionTakenOtherEl.addEventListener('input',  updatePreviewAll);
document.querySelector('textarea[name="recommendations"]').addEventListener('input', updatePreviewAll);

document.querySelector('.btn-add-date').addEventListener('click', function(e) {
    e.preventDefault();
    addDate();
});

document.getElementById('datePickerInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addDate();
    }
});

document.getElementById('minutesInputField').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addDate();
    }
});

document.getElementById('incidentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    Object.keys(editedContent).forEach(key => {
        formData.append(`edited_${key}`, editedContent[key]);
    });
    
    fetch('incident_report_submit.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.text())
    .then(data => {
        let res;
        try { 
            res = JSON.parse(data); 
        } catch(e) { 
            res = { ok: false, message: data }; 
        }

        if (!res.ok) {
            alert('Error: ' + (res.message || 'Unknown error'));
            return;
        }

        alert('Incident Report Submitted Successfully');

        if (res.pdf_url) {
            window.open(res.pdf_url, '_blank');
        }

        if (window.frameElement) {
            parent.location.reload();
        } else {
            window.close();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting form: ' + error.message);
    });
});

toggleCategorySections();
updateAttendanceDetails();
updatePreviewAll();
<?php
session_start();
require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        margin-left: 220px;
        padding: 20px;
        width: calc(100% - 260px);
        transition: margin-left 0.3s ease, width 0.3s ease;
    }
    .sidebar.collapsed + .main-content {
        margin-left: 80px;
        width: calc(100% - 80px);
    }

    .upload-card {
        background-color: #232330;
        border-radius: 10px;
        padding: 28px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        animation: fadeIn 0.5s ease-in-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .upload-card h2 {
        font-size: 15px;
        font-weight: 600;
        color: #f9fafb;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 13px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 18px;
    }
    .alert-info    { background: #1e3a5f; color: #93c5fd; border: 1px solid #2563eb; }
    .alert-success { background: #14532d; color: #86efac; border: 1px solid #16a34a; }
    .alert-danger  { background: #4c0519; color: #fca5a5; border: 1px solid #dc2626; }

    .upload-area {
        border: 2px dashed #4b4b5a;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        background: #1a1a27;
        transition: border-color 0.3s, background 0.3s;
    }
    .upload-area:hover, .upload-area.dragover {
        border-color: #3b82f6;
        background: #1e2a42;
    }
    .upload-area i   { font-size: 46px; color: #3b82f6; margin-bottom: 12px; }
    .upload-area p   { color: #d1d5db; font-size: 14px; margin-bottom: 4px; }
    .upload-area span{ color: #6b7280; font-size: 12px; }
    #csv-file-input  { display: none; }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        text-decoration: none;
    }
    .btn-primary  { background: linear-gradient(90deg,#7c3aed,#3b82f6); color:#fff; }
    .btn-primary:hover  { background: linear-gradient(90deg,#3b82f6,#2563eb); transform:translateY(-1px); }
    .btn-success  { background: linear-gradient(90deg,#059669,#10b981); color:#fff; }
    .btn-success:hover  { background: linear-gradient(90deg,#047857,#059669); transform:translateY(-1px); }
    .btn-secondary{ background: #4b4b5a; color:#e5e7eb; }
    .btn-secondary:hover{ background: #5a5a6e; }
    .btn-outline  { background:transparent; border:1px solid #3b82f6; color:#3b82f6; }
    .btn-outline:hover  { background:#1e2a42; }
    .btn-lg       { padding: 11px 26px; font-size: 14px; }
    .btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; }

    .action-row { display:flex; gap:10px; margin-top:18px; flex-wrap:wrap; align-items:center; }

    .file-info {
        background: #1e2a42;
        border: 1px solid #2563eb;
        border-radius: 8px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        margin-top: 14px;
    }
    .file-info i { color:#3b82f6; font-size:20px; }
    .file-info .file-name { font-weight:600; color:#e5e7eb; }
    .file-info .file-size { color:#6b7280; font-size:11px; }

    .preview-section {
        background: #232330;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        overflow: hidden;
        display: none;
        animation: fadeIn 0.4s ease-in-out;
    }
    .preview-header {
        padding: 18px 22px;
        border-bottom: 1px solid #3b3b4a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    .preview-header h2 {
        font-size: 15px;
        font-weight: 600;
        color: #f9fafb;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .stats-row {
        display: flex;
        gap: 20px;
        padding: 12px 22px;
        background: #1a1a27;
        border-bottom: 1px solid #3b3b4a;
        flex-wrap: wrap;
        font-size: 13px;
    }
    .stat-item .label { color:#9ca3af; }
    .stat-item .value { font-weight:700; color:#f9fafb; margin-left:5px; }

    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }
    .badge-primary { background:#1e3a5f; color:#93c5fd; }
    .badge-success { background:#14532d; color:#86efac; }
    .badge-danger  { background:#4c0519; color:#fca5a5; }

    .table-wrapper { overflow-x:auto; max-height:480px; overflow-y:auto; }
    table { width:100%; border-collapse:collapse; font-size:12.5px; }
    thead { position:sticky; top:0; background:#3b3b4a; z-index:10; }
    thead th { padding:11px 14px; text-align:left; font-weight:600; color:#f9fafb; white-space:nowrap; }
    tbody tr { border-bottom:1px solid #2e2e3d; }
    tbody tr:hover { background:rgba(59,130,246,0.08); }
    tbody td { padding:9px 14px; white-space:nowrap; color:#d1d5db; max-width:200px; overflow:hidden; text-overflow:ellipsis; }
    .row-num { color:#6b7280; font-size:11px; }
    .error-row td { color:#fca5a5 !important; }

    .confirm-area {
        padding: 18px 22px;
        border-top: 1px solid #3b3b4a;
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        background: #1a1a27;
    }
    #importNote { font-size:13px; color:#9ca3af; }

    /* ── Credentials Modal ── */
    .cred-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.80);
        z-index: 10000;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    .cred-overlay.active { display: flex; }
    .cred-modal {
        background: #1e1e2d;
        border: 1px solid #3b3b4a;
        border-radius: 14px;
        box-shadow: 0 12px 48px rgba(0,0,0,0.7);
        width: 100%;
        max-width: 900px;
        max-height: 88vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .cred-header {
        padding: 20px 24px 16px;
        border-bottom: 1px solid #3b3b4a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        background: #232330;
    }
    .cred-header h3 { margin:0; font-size:16px; font-weight:700; color:#f9fafb; display:flex; align-items:center; gap:9px; }
    .cred-header h3 i { color: #f59e0b; }
    .cred-badge { background:#1e3a5f; color:#93c5fd; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; }
    .cred-body { overflow-y:auto; padding:20px 24px; flex:1; }
    .cred-warning {
        background: #451a03;
        border: 1px solid #f59e0b;
        color: #fcd34d;
        border-radius: 8px;
        padding: 11px 16px;
        font-size: 13px;
        margin-bottom: 18px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
        line-height: 1.5;
    }
    .cred-warning i { flex-shrink:0; margin-top:2px; color:#f59e0b; }
    .cred-table-wrap { overflow-x:auto; border-radius:8px; border:1px solid #3b3b4a; }
    .cred-table { width:100%; border-collapse:collapse; font-size:13px; }
    .cred-table thead tr { background:#2e2e3d; }
    .cred-table thead th { padding:11px 16px; text-align:left; font-weight:600; color:#f9fafb; white-space:nowrap; }
    .cred-table tbody tr { border-top:1px solid #2e2e3d; }
    .cred-table tbody tr:hover { background:rgba(59,130,246,0.07); }
    .cred-table tbody td { padding:10px 16px; color:#d1d5db; white-space:nowrap; }
    .cred-table .col-num  { color:#6b7280; font-size:11px; }
    .cred-table .col-code { color:#93c5fd; font-weight:600; }
    .cred-table .col-user { color:#e5e7eb; font-family:monospace; }
    .cred-table .col-pass { font-family:monospace; font-size:14px; font-weight:700; color:#86efac; letter-spacing:0.08em; }
    .cred-footer { padding:14px 24px; border-top:1px solid #3b3b4a; background:#232330; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .cred-footer .note { font-size:12px; color:#6b7280; margin-left:auto; }

    .loading-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.65);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        gap: 15px;
        color: white;
    }
    .spinner {
        width: 48px; height: 48px;
        border: 5px solid rgba(255,255,255,0.2);
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <div style="font-size:14px;color:#d1d5db;">Importing employees, please wait...</div>
</div>

<div class="main-content">
    <div class="header-container" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <div>
            <!-- Breadcrumb -->
            <div style="font-size:12px; color:#9ca3af; margin-bottom:6px;">
                <a href="add_employee.php" style="color:#3b82f6; text-decoration:none; font-weight:600;">
                    <i class="fas fa-user-plus"></i> Add Employee
                </a>
                <span style="margin:0 6px; color:#4b4b5a;">›</span>
                <span style="color:#f9fafb; font-weight:600;"><i class="fas fa-file-import"></i> Import Employee</span>
            </div>
            <h1><i class="fas fa-file-import"></i> Import Employees</h1>
        </div>
    </div>

    <div class="content-container">

        <?php if (!empty($_SESSION['import_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($_SESSION['import_success']) ?></span>
            </div>
            <?php unset($_SESSION['import_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['import_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($_SESSION['import_error']) ?></span>
            </div>
            <?php unset($_SESSION['import_error']); ?>
        <?php endif; ?>

        <!-- Upload Card -->
        <div class="upload-card">
            <h2><i class="fas fa-cloud-upload-alt"></i> Upload CSV File</h2>

            <div class="alert alert-info">
                <i class="fas fa-info-circle" style="margin-top:2px;flex-shrink:0;"></i>
                <div>
                    Upload your <strong>HSNP Masterlist CSV file</strong>. The system will preview all employees before saving.
                    <strong>PAYROLL NO</strong> is used as the Employee ID.
                </div>
            </div>

            <div class="upload-area" id="uploadArea" onclick="document.getElementById('csv-file-input').click()">
                <i class="fas fa-file-csv"></i>
                <p><strong>Click to browse</strong> or drag & drop your CSV file here</p>
                <span>Supported format: .csv — HSNP Masterlist</span>
            </div>
            <input type="file" id="csv-file-input" accept=".csv" onchange="handleFileSelect(this)">

            <div id="fileInfo" style="display:none;"></div>

            <div class="action-row">
                <button class="btn btn-primary" id="previewBtn" onclick="previewCSV()" disabled>
                    <i class="fas fa-eye"></i> Preview Data
                </button>
                <button class="btn btn-secondary" onclick="clearFile()">
                    <i class="fas fa-times"></i> Clear
                </button>
                <a href="add_employee.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Add Employee
                </a>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="preview-section" id="previewSection">
            <div class="preview-header">
                <h2><i class="fas fa-table"></i> Preview — Employees to Import</h2>
                <div style="display:flex;gap:8px;flex-wrap:wrap;" id="badgeContainer"></div>
            </div>

            <div class="stats-row" id="statsRow"></div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Payroll No (ID)</th>
                            <th>Full Name</th>
                            <th>Position</th>
                            <th>Department</th>
                            <th>Date Hired</th>
                            <th>Gender</th>
                            <th>Birthdate</th>
                            <th>Email</th>
                            <th>Contact No</th>
                            <th>TIN No</th>
                            <th>SSS No</th>
                            <th>PhilHealth No</th>
                            <th>HDMF No</th>
                            <th>Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="previewBody"></tbody>
                </table>
            </div>

            <div class="confirm-area">
                <form id="importForm" method="POST" action="process_import.php">
                    <input type="hidden" name="import_data" id="importDataInput">
                    <button type="button" class="btn btn-success btn-lg" onclick="confirmImport()">
                        <i class="fas fa-check-circle"></i> Confirm & Import All Employees
                    </button>
                </form>
                <button class="btn btn-outline" onclick="clearFile()">
                    <i class="fas fa-redo"></i> Upload Different File
                </button>
                <span id="importNote"></span>
            </div>
        </div>

    </div>
</div>

<script>
let parsedRows = [], validRows = [], selectedFile = null;

// Fixed column indices for HSNP Masterlist CSV
const COL = {
    payroll:3, fullname:4, lastname:5, firstname:6, middlename:7,
    position:9, dept:10, hire:18, tin:23, hdmf:24, sss:25,
    philhealth:26, address:27, contact:28, gender:29, birthdate:30,
    emergName:57, emergNo:58, email:75
};

// Drag & drop
const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault(); uploadArea.classList.remove('dragover');
    if (e.dataTransfer.files[0]) processFile(e.dataTransfer.files[0]);
});

function handleFileSelect(i) { if (i.files[0]) processFile(i.files[0]); }

function processFile(file) {
    if (!file.name.toLowerCase().endsWith('.csv')) { alert('Please upload a .csv file.'); return; }
    selectedFile = file;
    const mb = (file.size/1024/1024).toFixed(2);
    document.getElementById('fileInfo').innerHTML = `
        <div class="file-info">
            <i class="fas fa-file-csv"></i>
            <div><div class="file-name">${file.name}</div><div class="file-size">${mb} MB</div></div>
        </div>`;
    document.getElementById('fileInfo').style.display = 'block';
    document.getElementById('previewBtn').disabled = false;
}

function clearFile() {
    selectedFile = null; parsedRows = []; validRows = [];
    document.getElementById('csv-file-input').value = '';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('previewBtn').disabled = true;
    document.getElementById('previewSection').style.display = 'none';
}

function previewCSV() {
    if (!selectedFile) return;
    const btn = document.getElementById('previewBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reading...';
    const reader = new FileReader();
    reader.onload = e => {
        try { parseAndPreview(e.target.result); }
        catch(err) { alert('Error: ' + err.message); }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-eye"></i> Preview Data';
    };
    reader.readAsText(selectedFile, 'utf-8');
}

function parseCSV(text) {
    const rows = []; let row = [], col = '', inQ = false;
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (ch === '"') {
            if (inQ && text[i+1] === '"') { col += '"'; i++; } else inQ = !inQ;
        } else if (ch === ',' && !inQ) {
            row.push(col); col = '';
        } else if ((ch === '\n' || ch === '\r') && !inQ) {
            if (ch === '\r' && text[i+1] === '\n') i++;
            row.push(col); col = ''; rows.push(row); row = [];
        } else col += ch;
    }
    if (col || row.length) { row.push(col); rows.push(row); }
    return rows;
}

function parseAndPreview(text) {
    if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
    const rawRows = parseCSV(text);
    if (rawRows.length < 2) { alert('CSV is empty or invalid.'); return; }

    const fmt = v => {
        v = (v||'').trim();
        if (!v) return '';
        const m = v.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
        if (m) { let y=parseInt(m[3]); if(y<100) y+=y>30?1900:2000; return `${y}-${m[1].padStart(2,'0')}-${m[2].padStart(2,'0')}`; }
        if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
        return '';
    };

    parsedRows = []; validRows = [];
    for (let i = 1; i < rawRows.length; i++) {
        const r = rawRows[i];
        if (!r || r.length < 10) continue;
        const get = idx => (idx < r.length ? r[idx] : '').replace(/\r/g,'').trim();

        const payroll = get(COL.payroll), fn = get(COL.firstname), ln = get(COL.lastname);
        if (!payroll && !fn && !ln) continue;

        let gender = get(COL.gender).toUpperCase();
        gender = gender==='M'?'Male':gender==='F'?'Female':'';

        const errors = [];
        if (!payroll) errors.push('Missing Payroll No');
        if (!fn && !ln) errors.push('Missing Name');

        const row = {
            employee_code:payroll, last_name:ln, first_name:fn,
            middle_name:get(COL.middlename),
            full_name:get(COL.fullname)||`${ln}, ${fn}`.trim(),
            position:get(COL.position), department:get(COL.dept),
            date_hired:fmt(get(COL.hire)), gender,
            birthdate:fmt(get(COL.birthdate)),
            email:get(COL.email), contact_no:get(COL.contact),
            tin_number:get(COL.tin), ss_number:get(COL.sss),
            philhealth_number:get(COL.philhealth), hmdf_number:get(COL.hdmf),
            present_address:get(COL.address),
            emergency_name:get(COL.emergName), emergency_no:get(COL.emergNo),
            errors, hasError:errors.length>0
        };
        parsedRows.push(row);
        if (!row.hasError) validRows.push(row);
    }
    renderPreview();
}

function renderPreview() {
    const total=parsedRows.length, valid=validRows.length, errs=total-valid;
    document.getElementById('badgeContainer').innerHTML=`
        <span class="badge badge-primary">${total} Total</span>
        <span class="badge badge-success">${valid} Valid</span>
        ${errs>0?`<span class="badge badge-danger">${errs} Errors</span>`:''}`;
    document.getElementById('statsRow').innerHTML=`
        <div class="stat-item"><span class="label">Total found:</span><span class="value">${total}</span></div>
        <div class="stat-item"><span class="label">Ready to import:</span><span class="value" style="color:#86efac">${valid}</span></div>
        ${errs>0?`<div class="stat-item"><span class="label">Skipped (errors):</span><span class="value" style="color:#fca5a5">${errs}</span></div>`:''}`;

    const tbody=document.getElementById('previewBody');
    tbody.innerHTML='';
    parsedRows.forEach((row,idx)=>{
        const tr=document.createElement('tr');
        if(row.hasError) tr.classList.add('error-row');
        const addr=row.present_address;
        tr.innerHTML=`
            <td class="row-num">${idx+1}</td>
            <td><strong style="color:#93c5fd">${row.employee_code||'—'}</strong></td>
            <td style="color:#f9fafb">${row.full_name}</td>
            <td>${row.position}</td><td>${row.department}</td>
            <td>${row.date_hired}</td><td>${row.gender}</td><td>${row.birthdate}</td>
            <td>${row.email}</td><td>${row.contact_no}</td>
            <td>${row.tin_number}</td><td>${row.ss_number}</td>
            <td>${row.philhealth_number}</td><td>${row.hmdf_number}</td>
            <td title="${addr}">${addr.substring(0,40)}${addr.length>40?'...':''}</td>
            <td>${row.hasError?`<span class="badge badge-danger">${row.errors.join(', ')}</span>`:`<span class="badge badge-success">✓ Valid</span>`}</td>`;
        tbody.appendChild(tr);
    });

    document.getElementById('importNote').textContent=
        `${valid} employee${valid!==1?'s':''} will be imported.`+
        (errs>0?` ${errs} row(s) with errors will be skipped.`:'');

    const sec=document.getElementById('previewSection');
    sec.style.display='block';
    sec.scrollIntoView({behavior:'smooth',block:'start'});
}

function confirmImport() {
    if (!validRows.length) { alert('No valid rows to import.'); return; }
    if (!confirm(`Import ${validRows.length} employee(s)?\n\nProceed?`)) return;
    const data=validRows.map(r=>({
        employee_code:r.employee_code, last_name:r.last_name, first_name:r.first_name,
        middle_name:r.middle_name, position:r.position, department:r.department,
        date_hired:r.date_hired, gender:r.gender, birthdate:r.birthdate,
        email:r.email, contact_no:r.contact_no, tin_number:r.tin_number,
        ss_number:r.ss_number, philhealth_number:r.philhealth_number,
        hmdf_number:r.hmdf_number, present_address:r.present_address,
        emergency_name:r.emergency_name, emergency_no:r.emergency_no,
    }));
    document.getElementById('importDataInput').value=JSON.stringify(data);
    document.getElementById('loadingOverlay').style.display='flex';
    document.getElementById('importForm').submit();
}
</script>

<!-- ══ Credentials Modal (auto-opens once after successful import) ════════════ -->
<?php if (!empty($_SESSION['import_credentials'])): ?>
<div class="cred-overlay active" id="credModal">
    <div class="cred-modal">

        <div class="cred-header">
            <h3><i class="fas fa-key"></i> New Employee Login Credentials</h3>
            <span class="cred-badge"><?= count($_SESSION['import_credentials']) ?> account<?= count($_SESSION['import_credentials']) !== 1 ? 's' : '' ?> created</span>
        </div>

        <div class="cred-body">
            <div class="cred-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Save these credentials now — they will NOT be shown again.</strong><br>
                    Download the CSV or copy the table and share each employee's username and password securely.
                    Passwords are stored as hashed values in the database; only the employee knows the plaintext.
                </div>
            </div>

            <div class="cred-table-wrap">
                <table class="cred-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Payroll / Employee ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Password <span style="font-weight:400;color:#9ca3af;font-size:11px;">(7 digits + 1 letter)</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['import_credentials'] as $i => $cred): ?>
                        <tr>
                            <td class="col-num"><?= $i + 1 ?></td>
                            <td class="col-code"><?= htmlspecialchars($cred['employee_code']) ?></td>
                            <td><?= htmlspecialchars($cred['full_name']) ?></td>
                            <td class="col-user"><?= htmlspecialchars($cred['username']) ?></td>
                            <td class="col-pass"><?= htmlspecialchars($cred['password']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cred-footer">
            <button class="btn btn-success" onclick="downloadCredCSV()">
                <i class="fas fa-file-csv"></i> Download CSV
            </button>
            <button class="btn btn-secondary" onclick="copyCredText()">
                <i class="fas fa-copy"></i> Copy All
            </button>
            <button class="btn btn-primary" onclick="document.getElementById('credModal').classList.remove('active')">
                <i class="fas fa-check"></i> Done, Close
            </button>
            <span class="note"><i class="fas fa-lock" style="margin-right:4px;"></i>Passwords are hashed in the database</span>
        </div>

    </div>
</div>

<script>
// Raw credential data from PHP (plaintext, shown once)
const CRED_DATA = <?= json_encode(array_values($_SESSION['import_credentials'])) ?>;

// ── Download as CSV ───────────────────────────────────────────────────────────
function downloadCredCSV() {
    const headers = ['Payroll ID', 'Full Name', 'Username', 'Password'];
    const rows = CRED_DATA.map(c => [
        c.employee_code,
        c.full_name,
        c.username,
        c.password
    ]);
    const csv = [headers, ...rows]
        .map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(','))
        .join('\r\n');

    const now     = new Date();
    const dateStr = now.getFullYear() + '-' +
                    String(now.getMonth()+1).padStart(2,'0') + '-' +
                    String(now.getDate()).padStart(2,'0');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a    = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(blob),
        download: `employee_credentials_${dateStr}.csv`
    });
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ── Copy as tab-separated text ────────────────────────────────────────────────
function copyCredText() {
    const header = 'Payroll ID\tFull Name\tUsername\tPassword';
    const rows   = CRED_DATA.map(c =>
        `${c.employee_code}\t${c.full_name}\t${c.username}\t${c.password}`
    );
    const text = [header, ...rows].join('\n');

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text)
            .then(() => alert('✅ Credentials copied to clipboard!'))
            .catch(() => fallbackCopy(text));
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const ta = Object.assign(document.createElement('textarea'), {
        value: text, style: 'position:fixed;opacity:0;'
    });
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    alert('✅ Credentials copied to clipboard!');
}
</script>
<?php unset($_SESSION['import_credentials']); ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin', 'manager'])) {
    header("Location: ../index.php");
    exit();
}

date_default_timezone_set('Asia/Manila');
$currentPage = 'cutoffs';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cut-off Periods</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">
    <link rel="stylesheet" href="admin_cutoff.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
</head>
<body>

<?php include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; ?>

    <div class="container-fluid h-100 px-3 pb-3 d-flex flex-column">
        <div class="row flex-grow-1 g-2 row-min-h">

            <!-- LEFT: CUT-OFF LIST -->
            <div class="col-4 d-flex flex-column min-h-0">
                <div class="card card-neutral flex-grow-1 d-flex flex-column min-h-0">

                    <div class="card-header">
                        <div class="hstack gap-2">
                            <h5 class="text-primary mb-0">Cut-Off List</h5>
                            <div class="dropdown ms-auto">
                                <button class="btn btn-sm btn-success dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-plus-lg"></i> Manage
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" type="button" id="btn-add-cutoff">
                                            <i class="bi bi-calendar-plus me-1"></i> Add Cut-Off Period
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item" type="button"
                                                data-bs-toggle="modal" data-bs-target="#importCutoffModal">
                                            <i class="bi bi-file-earmark-arrow-down me-1"></i> Import Cut-Offs
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="card-body flex-grow-1 d-flex flex-column p-0 min-h-0">

                        <!-- Loading -->
                        <div id="cutoff-loading" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
                        </div>

                        <!-- Empty State -->
                        <div id="cutoff-empty" class="cutoff-empty m-auto text-center p-3 d-none">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta mt-2 small">No cut-off period found.</div>
                            <button class="btn btn-sm btn-success mt-2" id="btn-add-cutoff-empty">
                                <i class="bi bi-plus-lg"></i> Create Cut-Off Period
                            </button>
                        </div>

                        <!-- Table -->
                        <div class="cutoff-table-scroll d-none" id="cutoff-table-scroll">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr></tr>
                                </thead>
                                <tbody id="cutoff-list"></tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT: PREVIEW PANEL -->
            <div class="col-8 d-flex flex-column min-h-0">
                <div class="card card-neutral flex-grow-1 d-flex flex-column min-h-0">

                    <div class="card-header">
                        <div class="hstack gap-2">
                            <h5 class="text-primary mb-0">Cut-Off Preview</h5>
                            <span class="text-tertiary small ms-auto" id="preview-label"></span>
                        </div>
                    </div>

                    <div class="card-body flex-grow-1 d-flex flex-column p-0 min-h-0">

                        <!-- Empty State -->
                        <div class="text-center calendar-preview-empty m-auto" id="preview-empty">
                            <i class="bi bi-calendar2-fill"></i>
                            <div class="text-meta mt-2">Select a cut-off period to preview.</div>
                        </div>

                        <!-- Calendar (always mounted, hidden until a cutoff is selected) -->
                        <div id="calendar-wrap" class="flex-grow-1 min-h-0 d-none">
                            <div id="fc-preview"></div>
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </div>

</div><!-- #main-wrapper -->

<?php include __DIR__ . '/../toast.php'; ?>

<!-- BULK IMPORT MODAL -->
<div class="modal fade" id="importCutoffModal" tabindex="-1" aria-labelledby="import-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="import-modal-title">Import Cut-Off Periods</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="import-cutoff-form" enctype="multipart/form-data">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Upload Excel File</label>
                        <label for="cutoff-file-input" class="schedule-dropzone w-100">
                            <div class="schedule-dropzone-icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                            <div class="schedule-dropzone-title">Drag & Drop your .xlsx file here</div>
                            <div class="schedule-dropzone-subtitle">or click to browse files</div>
                            <div class="schedule-dropzone-meta mt-3">Accepted format: <strong>.xlsx</strong></div>
                            <input type="file" name="cutoff_file" id="cutoff-file-input" accept=".xlsx" hidden>
                        </label>
                        <small class="text-secondary d-block mt-2">Preview will appear below after selecting a file.</small>
                    </div>

                    <div id="cutoff-file-preview" class="mt-3" style="display:none;">
                        <div class="rounded p-2" style="background:var(--frosted-bg);border:1px solid var(--frosted-border);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong style="color:var(--text-lightest);">File Preview</strong>
                                <span id="cutoff-file-name" class="small" style="color:var(--text-muted);"></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr><th>Start Date</th><th>End Date</th></tr>
                                    </thead>
                                    <tbody id="cutoff-preview-body"></tbody>
                                </table>
                            </div>
                            <small class="d-block mt-2" style="color:var(--text-muted);">Showing first 5 rows only</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center rounded p-3 mt-3"
                         style="background:var(--frosted-bg);border:1px solid var(--frosted-border);">
                        <small style="color:var(--text-muted);">Download the template to ensure correct format.</small>
                        <a href="bulk_importing_api.php?action=download_cutoff_template" class="btn btn-sm ms-3">
                            <i class="bi bi-download"></i> Template
                        </a>
                    </div>

                    <div id="import-result" class="mt-2 small d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success" id="btn-import-cutoff">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Import Cut-Offs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="delete-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="delete-modal-title">Delete Cut-Off Period</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-primary mb-0">Are you sure you want to delete the cut-off period for <span class="fw-semibold" id="delete-modal-range"></span>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="btn-confirm-delete">
                    <i class="bi bi-trash-fill me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ADD / EDIT MODAL -->
<div class="modal fade" id="cutoffModal" tabindex="-1" aria-labelledby="modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Add Cut-Off Period</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Start Date</label>
                    <input type="text" class="form-control" id="input-start" placeholder="Select start date" readonly>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-semibold">End Date</label>
                    <input type="text" class="form-control" id="input-end" placeholder="Select end date" readonly>
                </div>
                <div id="days-label" class="text-tertiary small mt-3 d-none"></div>
                <div id="modal-error" class="text-danger small mt-2 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success" id="btn-save-cutoff">
                    <i class="bi bi-floppy-fill me-1"></i> Save Cut-off Period
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const API = 'bulk_importing_api.php';
    let cutoffs        = [];
    let activeCutoffId = null;
    let calendarInst   = null;
    let editingId      = null;

    function localDateStr(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }

    function nextDay(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        return localDateStr(d);
    }

    function rangeEvents(startStr, endStr) {
        const events = [];
        const end = new Date(endStr + 'T00:00:00');
        const cur = new Date(startStr + 'T00:00:00');
        while (cur <= end) {
            const d = localDateStr(cur);
            events.push({ start: d, end: nextDay(d), display: 'background', color: 'rgba(151,190,65,0.30)' });
            cur.setDate(cur.getDate() + 1);
        }
        return events;
    }

    // ── HELPERS ──────────────────────────────────────────
    function fmtRange(start, end) {
        const s = new Date(start + 'T00:00:00');
        const e = new Date(end   + 'T00:00:00');
        const opts = { month: 'short', day: 'numeric' };
        const yrOpts = { month: 'short', day: 'numeric', year: 'numeric' };
        if (s.getFullYear() === e.getFullYear()) {
            return s.toLocaleDateString('en-US', opts) + ' – ' + e.toLocaleDateString('en-US', yrOpts);
        }
        return s.toLocaleDateString('en-US', yrOpts) + ' – ' + e.toLocaleDateString('en-US', yrOpts);
    }

    // ── LIST ─────────────────────────────────────────────
    async function loadCutoffs() {
        document.getElementById('cutoff-loading').classList.remove('d-none');
        document.getElementById('cutoff-empty').classList.add('d-none');
        document.getElementById('cutoff-list').innerHTML = '';

        const res = await fetch(API);
        cutoffs   = await res.json();

        document.getElementById('cutoff-loading').classList.add('d-none');
        renderList();
    }

    function renderList() {
        const list        = document.getElementById('cutoff-list');
        const empty       = document.getElementById('cutoff-empty');
        const tableScroll = document.getElementById('cutoff-table-scroll');
        list.innerHTML = '';

        if (!cutoffs.length) {
            empty.classList.remove('d-none');
            tableScroll.classList.add('d-none');
            return;
        }
        empty.classList.add('d-none');
        tableScroll.classList.remove('d-none');

        cutoffs.forEach(c => {
            const tr = document.createElement('tr');
            tr.className = 'cutoff-item';
            tr.dataset.id = c.id;
            if (c.id == activeCutoffId) tr.classList.add('active');
            tr.innerHTML = `
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="icon-box icon-box-sm flex-shrink-0">
                            <i class="bi bi-calendar-range"></i>
                        </div>
                        <span class="cutoff-label">${fmtRange(c.start_date, c.end_date)}</span>
                    </div>
                </td>
                <td class="cutoff-actions">
                    <button class="btn btn-info btn-sm btn-edit-item" data-id="${c.id}" title="Edit">
                        <i class="bi bi-pencil-fill text-info"></i>
                    </button>
                    <button class="btn btn-danger btn-sm btn-delete-item" data-id="${c.id}" title="Delete">
                        <i class="bi bi-trash-fill text-danger"></i>
                    </button>
                </td>
            `;

            tr.addEventListener('click', e => {
                if (e.target.closest('.btn-edit-item') || e.target.closest('.btn-delete-item')) return;
                selectCutoff(c.id);
            });

            tr.querySelector('.btn-edit-item').addEventListener('click', e => {
                e.stopPropagation();
                openEditModal(c.id);
            });

            tr.querySelector('.btn-delete-item').addEventListener('click', e => {
                e.stopPropagation();
                confirmDelete(c.id);
            });

            list.appendChild(tr);
        });
    }

    // ── SELECT / PREVIEW ─────────────────────────────────
    function deselectCutoff() {
        activeCutoffId = null;
        document.querySelectorAll('.cutoff-item').forEach(el => el.classList.remove('active'));
        document.getElementById('preview-label').textContent = '';
        if (calendarInst) { calendarInst.destroy(); calendarInst = null; }
        document.getElementById('calendar-wrap').classList.add('d-none');
        document.getElementById('preview-empty').classList.remove('d-none');
    }

    function selectCutoff(id) {
        if (id == activeCutoffId) { deselectCutoff(); return; }

        activeCutoffId = id;

        document.querySelectorAll('.cutoff-item').forEach(el =>
            el.classList.toggle('active', el.dataset.id == id)
        );

        const c = cutoffs.find(x => x.id == id);
        if (!c) return;

        document.getElementById('preview-empty').classList.add('d-none');
        document.getElementById('calendar-wrap').classList.remove('d-none');
        document.getElementById('preview-label').textContent = fmtRange(c.start_date, c.end_date);

        if (calendarInst) { calendarInst.destroy(); calendarInst = null; }

        calendarInst = new FullCalendar.Calendar(
            document.getElementById('fc-preview'),
            {
                initialView:          'multiMonth',
                multiMonthMaxColumns: 1,
                headerToolbar:        false,
                footerToolbar:        false,
                height:               'auto',
                initialDate:          c.start_date,
                visibleRange: {
                    start: c.start_date,
                    end:   nextDay(c.end_date),
                },
                selectable:           false,
                editable:             false,
                events:               rangeEvents(c.start_date, c.end_date),
            }
        );
        calendarInst.render();
    }

    // ── DELETE ───────────────────────────────────────────
    function confirmDelete(id) {
        const c = cutoffs.find(x => x.id == id);
        if (!c) return;
        pendingDeleteId = id;
        document.getElementById('delete-modal-range').textContent = fmtRange(c.start_date, c.end_date);
        bsDeleteModal.show();
    }

    // ── MODAL ─────────────────────────────────────────────
    function updateDaysLabel() {
        const start = document.getElementById('input-start').value;
        const end   = document.getElementById('input-end').value;
        const lbl   = document.getElementById('days-label');
        if (start && end && start <= end) {
            const days = Math.round((new Date(end + 'T00:00:00') - new Date(start + 'T00:00:00')) / 86400000) + 1;
            lbl.textContent = `${days} day${days !== 1 ? 's' : ''} selected`;
            lbl.classList.remove('d-none');
        } else {
            lbl.classList.add('d-none');
        }
    }

    const bsModal      = new bootstrap.Modal(document.getElementById('cutoffModal'));
    const bsDeleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    let   pendingDeleteId = null;

    document.getElementById('btn-confirm-delete').addEventListener('click', async () => {
        bsDeleteModal.hide();
        if (pendingDeleteId === null) return;
        const id = pendingDeleteId;
        pendingDeleteId = null;

        await fetch(`${API}?id=${id}`, { method: 'DELETE' });
        if (activeCutoffId == id) deselectCutoff();
        await loadCutoffs();
        showToast('Cut-off period deleted.', 'success');
    });
    const modalFpStart = flatpickr('#input-start', { dateFormat: 'Y-m-d', onChange: updateDaysLabel });
    const modalFpEnd   = flatpickr('#input-end',   { dateFormat: 'Y-m-d', onChange: updateDaysLabel });

    function openAddModal() {
        editingId = null;
        document.getElementById('modal-title').textContent = 'Add Cut-Off Period';
        document.getElementById('modal-error').classList.add('d-none');
        document.getElementById('days-label').classList.add('d-none');
        modalFpStart.clear();
        modalFpEnd.clear();
        bsModal.show();
    }

    function openEditModal(id) {
        const c = cutoffs.find(x => x.id == id);
        if (!c) return;
        editingId = id;
        document.getElementById('modal-title').textContent = 'Edit Cut-Off Period';
        document.getElementById('modal-error').classList.add('d-none');
        modalFpStart.setDate(c.start_date, false);
        modalFpEnd.setDate(c.end_date, false);
        updateDaysLabel();
        bsModal.show();
    }

    async function saveCutoff() {
        const start = document.getElementById('input-start').value;
        const end   = document.getElementById('input-end').value;

        if (!start || !end) {
            showToast('Please select both start and end dates.', 'danger');
            return;
        }
        if (start > end) {
            showToast('Start date must not be after end date.', 'danger');
            return;
        }

        // ← remove the errEl.classList.add('d-none') line that was here

        const method = editingId ? 'PUT' : 'POST';
        const url    = editingId ? `${API}?id=${editingId}` : API;

        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ start_date: start, end_date: end }),
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            showToast(data.error || 'An error occurred.', 'danger');
            return;
        }

        bsModal.hide();
        const savedId = editingId;
        await loadCutoffs();
        if (savedId) selectCutoff(savedId);
        showToast(savedId ? 'Cut-off period updated.' : 'Cut-off period added.', 'success');
    }

    // ── BIND ──────────────────────────────────────────────
    document.getElementById('btn-add-cutoff').addEventListener('click', openAddModal);
    document.getElementById('btn-add-cutoff-empty').addEventListener('click', openAddModal);
    document.getElementById('btn-save-cutoff').addEventListener('click', saveCutoff);

    document.getElementById('cutoff-table-scroll').addEventListener('click', e => {
        if (!e.target.closest('.cutoff-item')) deselectCutoff();
    });

    // ── BULK IMPORT ───────────────────────────────────────
    (function initCutoffImport() {
        const form      = document.getElementById('import-cutoff-form');
        const fileInput = document.getElementById('cutoff-file-input');
        const dropzone  = fileInput?.closest('label.schedule-dropzone');
        const resultEl  = document.getElementById('import-result');

        if (!form || !fileInput) return;

        if (dropzone) {
            ['dragenter', 'dragover'].forEach(ev =>
                dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.add('dragover'); })
            );
            ['dragleave', 'drop'].forEach(ev =>
                dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.remove('dragover'); })
            );
            dropzone.addEventListener('drop', e => {
                if (e.dataTransfer.files.length) {
                    const dt = new DataTransfer();
                    Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
                    fileInput.files = dt.files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
        }

        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const workbook = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                    const rows     = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]],
                                         { header: 1, raw: false, dateNF: 'yyyy-mm-dd' });
                    const tbody    = document.getElementById('cutoff-preview-body');
                    tbody.innerHTML = '';
                    const dataRows = rows.slice(1, 6);
                    if (!dataRows.length) {
                        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">No data rows found.</td></tr>';
                    } else {
                        dataRows.forEach(row => {
                            const tr = document.createElement('tr');
                            [0, 1].forEach(i => {
                                const td = document.createElement('td');
                                td.textContent = row[i] ?? '—';
                                tr.appendChild(td);
                            });
                            tbody.appendChild(tr);
                        });
                    }
                    document.getElementById('cutoff-file-name').textContent = file.name;
                    document.getElementById('cutoff-file-preview').style.display = 'block';
                    resultEl.classList.add('d-none');
                } catch {
                    resultEl.textContent = 'Could not read file. Make sure it is a valid .xlsx file.';
                    resultEl.className   = 'mt-2 small text-danger';
                }
            };
            reader.readAsArrayBuffer(file);
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!fileInput.files[0]) {
                resultEl.textContent = 'Please select a file first.';
                resultEl.className   = 'mt-2 small text-danger';
                return;
            }
            const btn      = document.getElementById('btn-import-cutoff');
            const origHTML = btn.innerHTML;
            btn.disabled   = true;
            btn.innerHTML  = '<span class="spinner-border spinner-border-sm" role="status"></span> Importing...';
            resultEl.classList.add('d-none');

            try {
                const res  = await fetch('bulk_importing_api.php?action=import_cutoffs', { method: 'POST', body: new FormData(this) });
                const data = await res.json();
                if (data.status === 'success') {
                    let msg = `${data.inserted} cut-off period${data.inserted !== 1 ? 's' : ''} imported.`;
                    if (data.errors?.length) {
                        const shown  = data.errors.slice(0, 3).map(r => `Row ${r.row} (${r.message})`).join(', ');
                        const extra  = data.errors.length > 3 ? ` +${data.errors.length - 3} more` : '';
                        msg += ` ${data.errors.length} skipped — ${shown}${extra}.`;
                    }
                    resultEl.textContent = msg;
                    resultEl.className   = `mt-2 small ${data.errors?.length ? 'text-warning' : 'text-success'}`;
                    await loadCutoffs();
                    showToast(msg, data.errors?.length ? 'warning' : 'success');
                } else {
                    resultEl.textContent = data.message || 'Import failed.';
                    resultEl.className   = 'mt-2 small text-danger';
                    showToast(data.message || 'Import failed.', 'danger');
                }
            } catch {
                resultEl.textContent = 'Something went wrong. Please try again.';
                resultEl.className   = 'mt-2 small text-danger';
                showToast('Something went wrong. Please try again.', 'danger');
            } finally {
                btn.disabled  = false;
                btn.innerHTML = origHTML;
                resultEl.classList.remove('d-none');
            }
        });

        document.getElementById('importCutoffModal')?.addEventListener('hidden.bs.modal', () => {
            fileInput.value = '';
            document.getElementById('cutoff-file-preview').style.display = 'none';
            document.getElementById('cutoff-preview-body').innerHTML      = '';
            resultEl.classList.add('d-none');
        });
    })();

    loadCutoffs();
});
</script>

</body>
</html>

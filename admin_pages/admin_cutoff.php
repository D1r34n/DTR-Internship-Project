<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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
</head>
<body>

<?php include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; ?>

    <div class="container-fluid h-100 px-3 pb-3 pt-2 d-flex flex-column">
        <div class="row flex-grow-1 g-2 row-min-h">

            <!-- LEFT: CUT-OFF LIST -->
            <div class="col-3 d-flex flex-column min-h-0">
                <div class="card card-neutral flex-grow-1 d-flex flex-column min-h-0">

                    <div class="card-header">
                        <div class="hstack gap-2">
                            <h5 class="text-primary mb-0">Cut-Off List</h5>
                            <button class="btn btn-sm btn-success ms-auto" id="btn-add-cutoff">
                                <i class="bi bi-plus-lg"></i> Add Cut-Off Period
                            </button>
                        </div>
                    </div>

                    <div class="card-body flex-grow-1 overflow-auto p-0 min-h-0">

                        <!-- Loading -->
                        <div id="cutoff-loading" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
                        </div>

                        <!-- Empty State -->
                        <div id="cutoff-empty" class="text-center cutoff-empty p-3 d-none">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta mt-2">No cut-off period found.</div>
                            <button class="btn btn-sm btn-success mt-2" id="btn-add-cutoff-empty">
                                <i class="bi bi-plus-lg"></i> Create Cut-Off Period
                            </button>
                        </div>

                        <!-- List -->
                        <ul class="list-group list-group-flush" id="cutoff-list"></ul>

                    </div>
                </div>
            </div>

            <!-- RIGHT: PREVIEW PANEL -->
            <div class="col-9 d-flex flex-column min-h-0">
                <div class="card card-neutral flex-grow-1 d-flex flex-column min-h-0">

                    <div class="card-header">
                        <div class="hstack gap-2">
                            <h5 class="text-primary mb-0">Cut-Off Preview</h5>
                            <span class="text-muted small ms-2" id="preview-label"></span>
                        </div>
                    </div>

                    <div class="card-body flex-grow-1 d-flex flex-column p-0 min-h-0">

                        <!-- Empty State -->
                        <div class="text-center calendar-preview-empty my-auto" id="preview-empty">
                            <i class="bi bi-calendar2-fill"></i>
                            <div class="text-meta mt-2">Select a cut-off period to preview.</div>
                        </div>

                        <!-- FullCalendar preview -->
                        <div id="calendar-wrap" class="d-none">
                            <div id="fc-preview"></div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>

</div><!-- #main-wrapper -->

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
                <div id="modal-error" class="text-danger small mt-2 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success" id="btn-save-cutoff">
                    <i class="bi bi-floppy-fill me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const API = 'cutoff_api.php';
    let cutoffs        = [];
    let activeCutoffId = null;
    let calendarInst   = null;
    let editingId      = null;

    function nextDay(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        return d.toISOString().slice(0, 10);
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
        const list  = document.getElementById('cutoff-list');
        const empty = document.getElementById('cutoff-empty');
        list.innerHTML = '';

        if (!cutoffs.length) {
            empty.classList.remove('d-none');
            return;
        }
        empty.classList.add('d-none');

        cutoffs.forEach(c => {
            const li = document.createElement('li');
            li.className = 'list-group-item cutoff-item d-flex align-items-center gap-2';
            li.dataset.id = c.id;
            if (c.id == activeCutoffId) li.classList.add('active');
            li.innerHTML = `
                <div class="icon-box icon-box-sm">
                    <i class="bi bi-calendar-range"></i>
                </div>

                <span class="flex-grow-1 small cutoff-label">
                    ${fmtRange(c.start_date, c.end_date)}
                </span>

                <button class="btn btn-info btn-sm btn-edit-item" data-id="${c.id}" title="Edit">
                    <i class="bi bi-pencil-fill text-info"></i>
                </button>

                <button class="btn btn-danger btn-sm btn-delete-item" data-id="${c.id}" title="Delete">
                    <i class="bi bi-trash-fill text-danger"></i>
                </button>
            `;

            li.addEventListener('click', e => {
                if (e.target.closest('.btn-edit-item') || e.target.closest('.btn-delete-item')) return;
                selectCutoff(c.id);
            });

            li.querySelector('.btn-edit-item').addEventListener('click', e => {
                e.stopPropagation();
                openEditModal(c.id);
            });

            li.querySelector('.btn-delete-item').addEventListener('click', e => {
                e.stopPropagation();
                confirmDelete(c.id);
            });

            list.appendChild(li);
        });
    }

    // ── SELECT / PREVIEW ─────────────────────────────────
    function selectCutoff(id) {
        activeCutoffId = id;
        document.querySelectorAll('.cutoff-item').forEach(el =>
            el.classList.toggle('active', el.dataset.id == id));

        const c = cutoffs.find(x => x.id == id);
        if (!c) return;

        document.getElementById('preview-empty').classList.add('d-none');
        document.getElementById('calendar-wrap').classList.remove('d-none');
        document.getElementById('preview-label').textContent = fmtRange(c.start_date, c.end_date);

        const event = {
            start:   c.start_date,
            end:     nextDay(c.end_date),
            display: 'background',
            color:   'rgba(151,190,65,0.30)',
        };

        if (!calendarInst) {
            calendarInst = new FullCalendar.Calendar(document.getElementById('fc-preview'), {
                initialView:         'multiMonth',
                multiMonthMaxColumns: 2,
                headerToolbar:       false,
                footerToolbar:       false,
                height:              '100%',
                initialDate:          c.start_date,
                selectable:          false,
                editable:            false,
                events:              [event],
            });
            calendarInst.render();
        } else {
            calendarInst.removeAllEvents();
            calendarInst.addEvent(event);
            calendarInst.gotoDate(c.start_date);
        }
    }

    // ── DELETE ───────────────────────────────────────────
    async function confirmDelete(id) {
        const c = cutoffs.find(x => x.id == id);
        if (!c || !confirm(`Delete cut-off period "${fmtRange(c.start_date, c.end_date)}"?`)) return;

        await fetch(`${API}?id=${id}`, { method: 'DELETE' });

        if (activeCutoffId == id) {
            activeCutoffId = null;
            document.getElementById('preview-empty').classList.remove('d-none');
            document.getElementById('calendar-wrap').classList.add('d-none');
            document.getElementById('preview-label').textContent = '';
            if (calendarInst) { calendarInst.destroy(); calendarInst = null; }
        }

        await loadCutoffs();
    }

    // ── MODAL ─────────────────────────────────────────────
    const bsModal     = new bootstrap.Modal(document.getElementById('cutoffModal'));
    const modalFpStart = flatpickr('#input-start', { dateFormat: 'Y-m-d' });
    const modalFpEnd   = flatpickr('#input-end',   { dateFormat: 'Y-m-d' });

    function openAddModal() {
        editingId = null;
        document.getElementById('modal-title').textContent = 'Add Cut-Off Period';
        document.getElementById('modal-error').classList.add('d-none');
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
        modalFpEnd.setDate(c.end_date,   false);
        bsModal.show();
    }

    async function saveCutoff() {
        const start = document.getElementById('input-start').value;
        const end   = document.getElementById('input-end').value;
        const errEl = document.getElementById('modal-error');

        if (!start || !end) {
            errEl.textContent = 'Please select both start and end dates.';
            errEl.classList.remove('d-none');
            return;
        }
        if (start > end) {
            errEl.textContent = 'Start date must not be after end date.';
            errEl.classList.remove('d-none');
            return;
        }

        errEl.classList.add('d-none');

        const method = editingId ? 'PUT' : 'POST';
        const url    = editingId ? `${API}?id=${editingId}` : API;

        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ start_date: start, end_date: end }),
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            errEl.textContent = data.error || 'An error occurred.';
            errEl.classList.remove('d-none');
            return;
        }

        bsModal.hide();
        const savedId = editingId;
        await loadCutoffs();
        if (savedId) selectCutoff(savedId);
    }

    // ── BIND ──────────────────────────────────────────────
    document.getElementById('btn-add-cutoff').addEventListener('click', openAddModal);
    document.getElementById('btn-add-cutoff-empty').addEventListener('click', openAddModal);
    document.getElementById('btn-save-cutoff').addEventListener('click', saveCutoff);

    loadCutoffs();
});
</script>

</body>
</html>

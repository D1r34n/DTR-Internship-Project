<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');

function getContrastColor(string $hex): string {
    $hex = str_replace('#', '', $hex);

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);

    return $luminance > 186 ? '#000000' : '#ffffff';
}
/*
-----------------------------------------
FETCH DEPARTMENTS (OPTIMIZED)
- includes parent name
- includes child count (NO N+1 QUERY)
-----------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.department_code,
        d.department_name,
        d.parent_id,
        d.color,
        p.department_name AS parent_name,

        COUNT(DISTINCT c.id) AS child_count,
        COUNT(DISTINCT e.id) AS employee_count

    FROM departments d
    LEFT JOIN departments p ON d.parent_id = p.id
    LEFT JOIN departments c ON c.parent_id = d.id
    LEFT JOIN employees e ON e.department_id = d.id

    GROUP BY
        d.id,
        d.department_code,
        d.department_name,
        d.parent_id,
        d.color,
        p.department_name

    ORDER BY d.department_name ASC
");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
/*
-----------------------------------------
DROPDOWN DATA (clean & separate)
-----------------------------------------
*/
$stmt2 = $pdo->query("
    SELECT id, department_name
    FROM departments
    ORDER BY department_name ASC
");
$departmentList = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Departments</title>

    <!-- 1. Bootstrap FIRST -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="admin_departments.css">

    <!-- 4. Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>

<?php $currentPage = 'departments'; include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; ?>

    <div class="card card-neutral logs-card">
        <div class="card-header departments-header">

            <span class="employee-title text-primary">
                <i class="bi bi-buildings"></i>
                Total Departments: <span id="emp-count"><?= count($departments) ?></span>
            </span>


            <div class="d-flex gap-2 align-items-center ms-auto flex-wrap">

                <!-- Sort + Search (hidden in chart view) -->
                <div id="grid-controls" class="d-flex gap-2 align-items-center">

                    <div class="dropdown w-30">
                        <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span id="sort-btn-label">Name (A → Z)</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item" type="button" onclick="selectSort('name_asc','Name (A → Z)')">Name (A → Z)</button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectSort('name_desc','Name (Z → A)')">Name (Z → A)</button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectSort('code_asc','Code (A → Z)')">Code (A → Z)</button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectSort('code_desc','Code (Z → A)')">Code (Z → A)</button></li>
                        </ul>
                    </div>
                    <input type="hidden" id="dept-sort-value" value="name_asc">

                    <div class="input-group" style="max-width: 220px;">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            type="text"
                            id="dept-search"
                            class="form-control"
                            placeholder="Search..."
                            oninput="applyFilterSort()">
                    </div>

                </div>

                <!-- View toggle -->
                <div class="btn-group view-toggle" role="group">
                    <button type="button" id="view-grid-btn" class="btn active"
                            onclick="switchView('grid')"
                            data-bs-toggle="popover"
                            data-bs-trigger="hover focus"
                            data-bs-content="Grid View"
                            data-bs-placement="bottom">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </button>
                    <button type="button" id="view-chart-btn" class="btn"
                            onclick="switchView('chart')"
                            data-bs-toggle="popover"
                            data-bs-trigger="hover focus"
                            data-bs-content="Org Chart"
                            data-bs-placement="bottom">
                        <i class="bi bi-diagram-3"></i>
                    </button>
                </div>

                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#create-dept-modal">
                    <i class="bi bi-plus-lg"></i> Add Department
                </button>
            </div>

        </div>
        <div class="card-body d-flex flex-column logs-card-body">

            <!-- GRID -->
            <div class="dept-grid">

                <?php foreach ($departments as $dept): ?>
                    <div class="dept-card">

                        <?php $color = $dept['color'] ?? '#4e73df'; ?>

                        <div class="dept-actions">
                            <i class="bi bi-pencil-square edit-dept-icon"
                            onclick='openEditDept(<?= json_encode($dept) ?>)'></i>
                        </div>

                        <div class="dept-identity">
                            <div class="dept-icon"
                                style="background: <?= htmlspecialchars($color) ?>;
                                        color: <?= getContrastColor($color) ?>;">
                                <?= htmlspecialchars($dept['department_code']) ?>
                            </div>

                            <div class="dept-name" title="<?= htmlspecialchars($dept['department_name']) ?>">
                                <?= htmlspecialchars($dept['department_name']) ?>
                            </div>
                        </div>

                        <?php if (!empty($dept['parent_name']) || $dept['child_count'] > 0): ?>
                            <div class="dept-pills">

                                <?php if (!empty($dept['parent_name'])): ?>
                                    <button
                                        class="dept-parent-link"
                                        onclick="viewParentDepartment(<?= $dept['parent_id'] ?>)"
                                        title="Under <?= htmlspecialchars($dept['parent_name']) ?>">
                                        <i class="bi bi-diagram-3"></i>
                                        <span>Under <?= htmlspecialchars($dept['parent_name']) ?></span>
                                    </button>
                                <?php endif; ?>

                                <?php if ($dept['child_count'] > 0): ?>
                                    <button
                                        class="dept-sub-btn"
                                        onclick="viewSubDepartments(
                                            <?= $dept['id'] ?>,
                                            '<?= htmlspecialchars($dept['department_name'], ENT_QUOTES) ?>'
                                        )">
                                        <i class="bi bi-diagram-2"></i>
                                        <?= $dept['child_count'] ?> Sub <?= $dept['child_count'] != 1 ? 'Departments' : 'Department' ?>
                                    </button>
                                <?php endif; ?>

                            </div>
                        <?php endif; ?>

                        <?php if ($dept['employee_count'] > 0): ?>
                        <a class="dept-meta" href="admin_manage_employees.php?dept=<?= $dept['id'] ?>">
                        <?php else: ?>
                        <span class="dept-meta dept-meta--empty">
                        <?php endif; ?>
                            <i class="bi bi-people"></i>
                            <?= $dept['employee_count'] ?> <?= $dept['employee_count'] != 1 ? 'Employees' : 'Employee' ?>
                        <?php if ($dept['employee_count'] > 0): ?>
                        </a>
                        <?php else: ?>
                        </span>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>

            </div>

            <!-- ORG CHART VIEW (hidden by default) -->
            <div id="dept-chart-wrap" style="display:none; flex:1; min-height:0; overflow:hidden; padding-right:1.5rem;"
                 oncontextmenu="return false">
                <div id="dept-chart" style="width:100%; height:100%;"></div>
            </div>

        </div>
    </div>

    <!-- CREATE DEPARTMENT MODAL -->
    <div class="modal fade" id="create-dept-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Create Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <form id="create-dept-form">

                        <div class="create-dept-layout">

                            <!-- LEFT: PREVIEW -->
                            <div class="create-dept-preview">
                                <div class="create-dept-preview-icon" id="preview-icon"></div>
                                <div class="create-dept-preview-name" id="preview-name">Department Name</div>
                                <label class="color-pick-wrapper">
                                    <input type="color" class="color-pick-input" name="color" id="color-picker" value="#4e73df">
                                    <span id="color-hex-display" class="text-meta">#4e73df</span>
                                </label>
                            </div>

                            <!-- RIGHT: FIELDS -->
                            <div class="create-dept-fields">

                                <div class="mb-3">
                                    <label class="form-label">Department Code</label>
                                    <input type="text" class="form-control" name="department_code" id="input-code" placeholder="e.g. IT" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Department Name</label>
                                    <input type="text" class="form-control" name="department_name" id="input-name" placeholder="e.g. Information Technology" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        Parent Department
                                        <small class="text-meta">(Optional)</small>
                                    </label>
                                    <div class="dropdown w-100">
                                        <button class="btn w-100 text-start dropdown-toggle"
                                                type="button"
                                                data-bs-toggle="dropdown"
                                                data-bs-auto-close="outside"
                                                aria-expanded="false"
                                                id="create-parent-btn">
                                            <span id="create-parent-label">None</span>
                                        </button>
                                        <div class="dropdown-menu w-100 p-2">
                                            <input type="text"
                                                   class="form-control form-control-sm mb-2"
                                                   id="create-parent-search"
                                                   placeholder="Search department...">
                                            <ul class="list-unstyled mb-0"
                                                id="create-parent-list"
                                                style="max-height:200px; overflow-y:auto;">
                                            </ul>
                                        </div>
                                    </div>
                                    <input type="hidden" name="parent_id" id="create-parent-id" value="">
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-plus-lg"></i> Create Department
                                </button>

                            </div>

                        </div>

                    </form>

                    <div id="dept-msg" class="mt-2 text-center"></div>

                </div>

            </div>
        </div>
    </div>
    
    <!-- EDIT DEPARTMENT MODAL -->
    <div class="modal fade" id="edit-dept-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <form id="edit-dept-form">

                        <input type="hidden" name="id" id="edit-dept-id">

                        <div class="create-dept-layout">

                            <!-- LEFT: PREVIEW -->
                            <div class="create-dept-preview">
                                <div class="create-dept-preview-icon" id="edit-preview-icon"></div>
                                <div class="create-dept-preview-name" id="edit-preview-name">Department Name</div>
                                <label class="color-pick-wrapper">
                                    <input type="color" class="color-pick-input" name="color" id="edit-color-picker" value="#4e73df">
                                    <span id="edit-color-hex-display" class="text-meta">#4e73df</span>
                                </label>
                            </div>

                            <!-- RIGHT: FIELDS -->
                            <div class="create-dept-fields">

                                <div class="mb-3">
                                    <label class="form-label">Department Code</label>
                                    <input type="text" class="form-control" name="department_code" id="edit-input-code" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Department Name</label>
                                    <input type="text" class="form-control" name="department_name" id="edit-input-name" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        Parent Department
                                        <small class="text-meta">(Optional)</small>
                                    </label>
                                    <input type="hidden" name="parent_id" id="edit-parent-id" value="">
                                    <input type="text" class="form-control" id="edit-parent-input"
                                           list="edit-parent-list" placeholder="None"
                                           autocomplete="off">
                                    <datalist id="edit-parent-list">
                                        <?php foreach ($departmentList as $row): ?>
                                            <option value="<?= htmlspecialchars($row['department_name']) ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="d-flex gap-2 flex-row">          
                                    <button type="button" class="btn btn-danger w-100" onclick="deleteDept()">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>   

                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-floppy"></i> Save
                                    </button>
                                </div>
                            </div>

                        </div>

                    </form>

                    <div id="edit-msg" class="mt-2 text-center"></div>

                </div>

            </div>
        </div>
    </div>

    <!-- PARENT DEPT MODAL -->
    <div class="modal fade" id="parent-dept-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Parent Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" id="parent-dept-body">
                    Loading...
                </div>

            </div>
        </div>
    </div>

    <!-- SUB DEPT MODAL -->
    <div class="modal fade" id="sub-dept-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="sub-dept-title">Sub Departments</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="sub-dept-list" class="sub-dept-list"></div>
                </div>

            </div>
        </div>
    </div>

    <!-- DELETE CONFIRMATION MODAL -->
    <div class="modal fade" id="delete-confirm-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-light mb-0">Delete <strong id="delete-dept-name" class="text-tertiary"></strong>? This cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-danger" id="delete-confirm-btn">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- #main-wrapper -->

<!-- JS -->
<script>
const form = document.getElementById('create-dept-form');
const msg  = document.getElementById('dept-msg');
const grid = document.querySelector('.dept-grid');

document.addEventListener('DOMContentLoaded', () => {

    /* ------------------------------------------------
       CREATE MODAL — LIVE PREVIEW
       ------------------------------------------------ */
    const inputCode   = document.getElementById('input-code');
    const inputName   = document.getElementById('input-name');
    const colorPicker = document.getElementById('color-picker');
    const previewIcon = document.getElementById('preview-icon');
    const previewName = document.getElementById('preview-name');

    function updatePreview() {
        const code  = inputCode.value.trim() || '';
        const name  = inputName.value.trim() || 'Department Name';
        const color = colorPicker.value      || '#4e73df';

        previewIcon.textContent      = code;
        previewIcon.style.background = color;
        previewIcon.style.color      = getContrastColor(color);
        previewName.textContent      = name;
        document.getElementById('color-hex-display').textContent = color.toUpperCase();
    }

    inputCode.addEventListener('input',   updatePreview);
    inputName.addEventListener('input',   updatePreview);
    colorPicker.addEventListener('input', updatePreview);

    updatePreview();

    /* ------------------------------------------------
       EDIT MODAL — LIVE PREVIEW
       ------------------------------------------------ */
    const editCode  = document.getElementById('edit-input-code');
    const editName  = document.getElementById('edit-input-name');
    const editColor = document.getElementById('edit-color-picker');
    const editIcon  = document.getElementById('edit-preview-icon');
    const editPrev  = document.getElementById('edit-preview-name');

    function updateEditPreview() {
        const code  = editCode.value.trim() || '';
        const name  = editName.value.trim() || 'Department Name';
        const color = editColor.value       || '#4e73df';

        editIcon.textContent      = code;
        editIcon.style.background = color;
        editIcon.style.color      = getContrastColor(color);
        editPrev.textContent      = name;
        document.getElementById('edit-color-hex-display').textContent = color.toUpperCase();
    }

    editCode.addEventListener('input', function () { this.value = this.value.toUpperCase(); updateEditPreview(); });
    editName.addEventListener('input',  updateEditPreview);
    editColor.addEventListener('input', updateEditPreview);

    /* View toggle popovers */
    document.querySelectorAll('.view-toggle [data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el, { trigger: 'hover focus' });
    });

});

const deptNameToId = <?= json_encode(array_column($departmentList, 'id', 'department_name')) ?>;
const deptListAll  = <?= json_encode(array_values($departmentList)) ?>;

/* ---- Create-modal parent searchable dropdown ---- */
(function () {
    const search  = document.getElementById('create-parent-search');
    const list    = document.getElementById('create-parent-list');
    const hiddenId = document.getElementById('create-parent-id');
    const label   = document.getElementById('create-parent-label');

    function renderList(q) {
        const filtered = q
            ? deptListAll.filter(d => d.department_name.toLowerCase().includes(q.toLowerCase()))
            : deptListAll;

        list.innerHTML = filtered.length
            ? filtered.map(d =>
                `<li><button type="button" class="dropdown-item" data-id="${d.id}" data-name="${d.department_name.replace(/"/g,'&quot;')}">${d.department_name}</button></li>`
              ).join('')
            : '<li><span class="dropdown-item text-muted">No results</span></li>';
    }

    renderList('');

    search.addEventListener('input', () => renderList(search.value));

    list.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-id]');
        if (!btn) return;
        hiddenId.value = btn.dataset.id;
        label.textContent = btn.dataset.name;
        bootstrap.Dropdown.getInstance(document.getElementById('create-parent-btn'))?.hide();
    });

    // Clear selection when modal resets
    document.getElementById('create-dept-modal').addEventListener('hidden.bs.modal', () => {
        hiddenId.value  = '';
        label.textContent = 'None';
        search.value    = '';
        renderList('');
    });
})();

/* ---- Edit-modal parent text input ---- */
document.getElementById('edit-parent-input').addEventListener('input', function () {
    document.getElementById('edit-parent-id').value = deptNameToId[this.value.trim()] ?? '';
});

form.addEventListener('submit', function(e) {
    e.preventDefault();

    const btn      = form.querySelector('[type="submit"]');
    const origHTML = btn.innerHTML;
    btn.disabled   = true;
    btn.innerHTML  = '<span class="spinner-border spinner-border-sm" role="status"></span>';

    fetch('department_api.php?action=create', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            form.reset();

            const color = data.color || '#4e73df';
            const div   = document.createElement('div');
            div.className = 'dept-card';
            div.innerHTML = `
                <div class="dept-actions">
                    <i class="bi bi-pencil-square edit-dept-icon"
                        onclick='openEditDept(${JSON.stringify(data)})'></i>
                </div>
                <div class="dept-identity">
                    <div class="dept-icon" style="background:${color}; color:${getContrastColor(color)};">
                        ${data.department_code}
                    </div>
                    <div class="dept-name">${data.department_name}</div>
                </div>
                ${data.parent_id && data.parent_name ? `
                    <div class="dept-pills">
                        <button class="dept-parent-link" onclick="viewParentDepartment(${data.parent_id})">
                            <i class="bi bi-diagram-3"></i>
                            Under ${data.parent_name}
                        </button>
                    </div>
                ` : ''}
                <a class="dept-meta" href="admin_manage_employees.php?dept=${data.id}">
                    <i class="bi bi-people"></i>
                    0 Employees
                </a>
            `;
            grid.appendChild(div);

            bootstrap.Modal.getInstance(document.getElementById('create-dept-modal')).hide();
            showToast('Department created successfully.', 'success');
        } else {
            form.department_code.classList.remove('is-invalid');
            form.department_name.classList.remove('is-invalid');
            if (data.message.toLowerCase().includes('code')) form.department_code.classList.add('is-invalid');
            if (data.message.toLowerCase().includes('name')) form.department_name.classList.add('is-invalid');
            showToast(data.message, 'danger');
        }
    })
    .catch(() => showToast('Something went wrong. Please try again.', 'danger'))
    .finally(() => {
        btn.disabled  = false;
        btn.innerHTML = origHTML;
    });
});

/* SORT */
const deptSortHidden = document.getElementById('dept-sort-value');

function selectSort(value, label) {
    deptSortHidden.value = value;
    document.getElementById('sort-btn-label').textContent = label;
    applyFilterSort();
}

function applyFilterSort() {
    let cards = Array.from(document.querySelectorAll('.dept-card'));
    const query = document.getElementById('dept-search').value.toLowerCase().trim();

    cards.forEach(card => {
        const name = card.querySelector('.dept-name')?.textContent.toLowerCase() || '';
        const code = card.querySelector('.dept-icon')?.textContent.toLowerCase() || '';
        card.style.display = (name.includes(query) || code.includes(query)) ? '' : 'none';
    });

    const container = document.querySelector('.dept-grid');

    cards.sort((a, b) => {
        const aName = a.querySelector('.dept-name').textContent.trim().toLowerCase();
        const bName = b.querySelector('.dept-name').textContent.trim().toLowerCase();
        const aCode = a.querySelector('.dept-icon').textContent.trim().toLowerCase();
        const bCode = b.querySelector('.dept-icon').textContent.trim().toLowerCase();

        switch (deptSortHidden.value) {
            case 'name_asc':  return aName.localeCompare(bName);
            case 'name_desc': return bName.localeCompare(aName);
            case 'code_asc':  return aCode.localeCompare(bCode);
            case 'code_desc': return bCode.localeCompare(aCode);
        }
    });

    cards.forEach(c => container.appendChild(c));
}

form.department_code.addEventListener('input', function () {
    this.value = this.value.toUpperCase();
    form.department_code.classList.remove('is-invalid');
});

form.department_name.addEventListener('input', () => {
    form.department_name.classList.remove('is-invalid');
});

// PARENT DEPARTMENT
function viewParentDepartment(parentId) {

    const body = document.getElementById('parent-dept-body');
    body.innerHTML = 'Loading...';

    fetch(`department_api.php?action=get_parent&id=${parentId}`)
        .then(res => res.json())
        .then(data => {

            if (!data) {
                body.innerHTML = '<div class="text-muted">No parent department found</div>';
                return;
            }

            body.innerHTML = `
                <div class="card-neutral dept-list-item">
                    <div class="dept-icon" style="background:${data.color || '#4e73df'}; color:${getContrastColor(data.color || '#4e73df')};">
                        ${data.department_code}
                    </div>
                    <div class="dept-name">${data.department_name}</div>
                </div>
            `;
        });

    new bootstrap.Modal(document.getElementById('parent-dept-modal')).show();
}

/* SUB DEPARTMENTS */
function viewSubDepartments(id, name) {

    document.getElementById('sub-dept-title').innerText =
        `Sub Departments of ${name}`;

    const list = document.getElementById('sub-dept-list');
    list.innerHTML = 'Loading...';

    fetch(`department_api.php?action=get_sub&id=${id}`)
        .then(res => res.json())
        .then(data => {

            if (!data.length) {
                list.innerHTML = '<div class="text-muted">No sub departments</div>';
                return;
            }

            list.innerHTML = data.map(d => `
                <div class="dept-list-item">
                    <div class="dept-icon" style="background:${d.color || '#4e73df'}; color:${getContrastColor(d.color || '#4e73df')};">
                        ${d.department_code}
                    </div>
                    <div class="dept-name">${d.department_name}</div>
                </div>
            `).join('');
        });

    new bootstrap.Modal(document.getElementById('sub-dept-modal')).show();
}

let currentEditId   = null;
let currentEditName = null;

function openEditDept(dept) {
    currentEditId   = dept.id;
    currentEditName = dept.department_name;

    document.getElementById('edit-dept-id').value          = dept.id;
    document.getElementById('edit-input-code').value       = dept.department_code;
    document.getElementById('edit-input-name').value       = dept.department_name;
    document.getElementById('edit-color-picker').value     = dept.color || '#4e73df';
    document.getElementById('edit-preview-icon').textContent   = dept.department_code;
    document.getElementById('edit-preview-icon').style.background = dept.color || '#4e73df';
    document.getElementById('edit-preview-icon').style.color      = getContrastColor(dept.color || '#4e73df');
    document.getElementById('edit-preview-name').textContent  = dept.department_name;

    document.getElementById('edit-parent-id').value    = dept.parent_id || '';
    document.getElementById('edit-parent-input').value = dept.parent_name || '';

    new bootstrap.Modal(document.getElementById('edit-dept-modal')).show();
}

document.getElementById('edit-dept-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn      = this.querySelector('[type="submit"]');
    const origHTML = btn.innerHTML;
    btn.disabled   = true;
    btn.innerHTML  = '<span class="spinner-border spinner-border-sm" role="status"></span>';

    fetch('department_api.php?action=update', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('edit-dept-modal'))?.hide();
            showToast('Department updated successfully.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message, 'danger');
            btn.disabled  = false;
            btn.innerHTML = origHTML;
        }
    })
    .catch(() => {
        showToast('Something went wrong. Please try again.', 'danger');
        btn.disabled  = false;
        btn.innerHTML = origHTML;
    });
});

function deleteDept() {
    document.getElementById('delete-dept-name').textContent = currentEditName || 'this department';
    new bootstrap.Modal(document.getElementById('delete-confirm-modal')).show();
}

document.getElementById('delete-confirm-btn').addEventListener('click', function () {
    bootstrap.Modal.getInstance(document.getElementById('delete-confirm-modal')).hide();

    fetch('department_api.php?action=delete', {
        method: 'POST',
        body: new URLSearchParams({ id: currentEditId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            document.getElementById('edit-msg').innerHTML =
                `<span class="text-danger">${data.message}</span>`;
        }
    });
});

/* -----------------------------------------------
   VIEW TOGGLE  (Grid ↔ Org Chart)
----------------------------------------------- */
let chartInited = false;

function switchView(view) {
    const isChart = view === 'chart';
    document.querySelector('.dept-grid').style.display        = isChart ? 'none' : '';
    document.getElementById('dept-chart-wrap').style.display = isChart ? ''     : 'none';
    document.getElementById('grid-controls').classList.toggle('d-none', isChart);
    document.getElementById('view-grid-btn').classList.toggle('active',  !isChart);
    document.getElementById('view-chart-btn').classList.toggle('active',  isChart);
    localStorage.setItem('deptView', view);
    if (isChart && !chartInited) loadOrgChart();
}

// Restore last view on page load
(function () {
    const saved = localStorage.getItem('deptView');
    if (saved === 'chart') switchView('chart');
})();

function loadOrgChart() {
    if (window.OrgChart) { initOrgChart(); return; }
    const s   = document.createElement('script');
    s.src     = 'https://balkan.app/js/OrgChart.js';
    s.onload  = initOrgChart;
    document.head.appendChild(s);
}

function initOrgChart() {
    chartInited = true;

    const depts = <?= json_encode(array_map(function($d) {
        return [
            'id'              => (int)$d['id'],
            'pid'             => $d['parent_id'] ? (int)$d['parent_id'] : null,
            'name'            => $d['department_name'],
            'code'            => $d['department_code'],
            'color'           => $d['color'] ?? '#4e73df',
            'emp'             => (int)$d['employee_count'] . ' ' . ((int)$d['employee_count'] === 1 ? 'Employee' : 'Employees'),
            // fields needed by openEditDept()
            'department_name' => $d['department_name'],
            'department_code' => $d['department_code'],
            'parent_id'       => $d['parent_id'],
            'parent_name'     => $d['parent_name'] ?? '',
            'employee_count'  => (int)$d['employee_count'],
        ];
    }, $departments), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Base template
    OrgChart.templates.deptCard = Object.assign({}, OrgChart.templates.base);
    OrgChart.templates.deptCard.size       = [220, 90];
    OrgChart.templates.deptCard.node       =
        '<defs><clipPath id="nc-{id}"><rect x="90" y="20" width="122" height="28"/></clipPath></defs>' +
        '<rect rx="12" x="0" y="0" height="90" width="220" fill="rgba(0,0,0,0.4)" stroke="rgba(255,255,255,0.08)" stroke-width="1" class="node-bkg"></rect>' +
        '<text x="90" y="74" font-family="bootstrap-icons" font-size="12" dominant-baseline="central" class="node-emp-icon">&#xF4D0;</text>';
    OrgChart.templates.deptCard.field_0    = '<circle cx="41" cy="41" r="26" fill="{val}"></circle>';
    OrgChart.templates.deptCard.field_1    = '<text x="41" y="45" text-anchor="middle" style="font-size:9px;font-weight:700;font-family:Poppins,sans-serif;" fill="#ffffff">{val}</text>';
    OrgChart.templates.deptCard.field_2    = '<text x="90" y="35" text-anchor="start" clip-path="url(#nc-{id})" style="font-size:12px;font-weight:500;font-family:Poppins,sans-serif;" class="node-text-name">{val}</text>';
    OrgChart.templates.deptCard.field_3    = '<text x="105" y="74" text-anchor="start" dominant-baseline="central" style="font-size:10px;font-family:Poppins,sans-serif;" class="node-emp-text">{val}</text>';
    OrgChart.templates.deptCard.editBtn    = '';
    OrgChart.templates.deptCard.menuButton = '';

    // Variant for light-colored dept circles — code text becomes black
    OrgChart.templates.deptCardAlt = Object.assign({}, OrgChart.templates.deptCard);
    OrgChart.templates.deptCardAlt.field_1 = '<text x="41" y="45" text-anchor="middle" style="font-size:9px;font-weight:700;font-family:Poppins,sans-serif;" fill="#000000">{val}</text>';

    // Truncate long text and tag nodes that need dark circle text
    const nodes = depts.map(d => ({
        ...d,
        shortName: d.name.length > 20 ? d.name.slice(0, 18) + '…' : d.name,
        shortCode: d.code.length > 5  ? d.code.slice(0, 4)  + '…' : d.code,
        tags: getContrastColor(d.color) === '#000000' ? ['alt'] : [],
    }));

    const chart = new OrgChart(document.getElementById('dept-chart'), {
        template: 'deptCard',
        tags: { alt: { template: 'deptCardAlt' } },
        nodeBinding: {
            field_0: 'color',
            field_1: 'shortCode',
            field_2: 'shortName',
            field_3: 'emp',
        },
        nodes,
        enableSearch: false,
        editUI: false,
        nodeMenu: null,
        menu: null,
        toolbar: { zoom: true, fit: true, expandAll: false },
        scaleInitial: OrgChart.match.boundary,
        layout: OrgChart.layout.normal,
        linkType: 'curve',
        zoom: { speed: 130, smooth: 10 },
        mouseScrool: OrgChart.action.zoom,
    });

    chart.on('click', function(sender, args) {
        const d = depts.find(n => n.id === args.node.id);
        if (d) openEditDept(d);
        return false;
    });
}

function getContrastColor(hex) {
    hex = hex.replace('#', '');

    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);

    const luminance = (0.299 * r + 0.587 * g + 0.114 * b);

    return luminance > 186 ? '#000000' : '#ffffff';
}
</script>

<?php include __DIR__ . '/../system_functions/show_toast.php'; ?>
</body>
</html>

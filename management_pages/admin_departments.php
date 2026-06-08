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

function getContrastColor($hex) {
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
    <script src="https://balkan.app/js/OrgChart.js"></script>
</head>

<body>

<?php $currentPage = 'departments'; include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; ?>

    <div class="card card-neutral logs-card">
        <div class="card-body d-flex flex-column logs-card-body">

            <!-- Filter Section -->
            <div class="dept-header">
                <span class="employee-title text-primary">
                    <i class="bi bi-buildings"></i>
                    Total Departments: <span id="emp-count"><?= count($departments) ?></span>
                </span>
                <div class="d-flex gap-2 align-items-center ms-auto flex-wrap">

                    <!-- Sort dropdown -->
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

                    <!-- Search -->
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

                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#create-dept-modal">
                        <i class="bi bi-plus-lg"></i> Add Department
                    </button>
                </div>
            </div>

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

                            <div class="dept-name">
                                <?= htmlspecialchars($dept['department_name']) ?>
                            </div>
                        </div>

                        <?php if (!empty($dept['parent_name']) || $dept['child_count'] > 0): ?>
                            <div class="dept-pills">

                                <?php if (!empty($dept['parent_name'])): ?>
                                    <button
                                        class="dept-parent-link"
                                        onclick="viewParentDepartment(<?= $dept['parent_id'] ?>)">
                                        <i class="bi bi-diagram-3"></i>
                                        Under <?= htmlspecialchars($dept['parent_name']) ?>
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
                                    Color
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
                                        <small class="text-muted">(Optional)</small>
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
                                    Color
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
                                        <small class="text-muted">(Optional)</small>
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
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-floppy"></i> Save
                                    </button>

                                    <button type="button" class="btn btn-danger w-100" onclick="deleteDept()">
                                        <i class="bi bi-trash"></i> Delete
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
    }

    editCode.addEventListener('input',  updateEditPreview);
    editName.addEventListener('input',  updateEditPreview);
    editColor.addEventListener('input', updateEditPreview);

});

const deptNameToId = <?= json_encode(array_column($departmentList, 'id', 'department_name')) ?>;

document.getElementById('create-parent-input').addEventListener('input', function () {
    document.getElementById('create-parent-id').value = deptNameToId[this.value.trim()] ?? '';
});

document.getElementById('edit-parent-input').addEventListener('input', function () {
    document.getElementById('edit-parent-id').value = deptNameToId[this.value.trim()] ?? '';
});

form.addEventListener('submit', function(e) {
    e.preventDefault();

    fetch('department_api.php?action=create', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(res => res.json())
    .then(data => {

        if (data.success) {

            msg.innerHTML = `<span class="text-success">${data.message}</span>`;
            form.reset();

            const div = document.createElement('div');
            div.className = 'dept-card';

            const color = data.color || '#4e73df';

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

            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('create-dept-modal')).hide();
                msg.innerHTML = '';
            }, 800);

        } else {
            msg.innerHTML = `<span class="text-danger">${data.message}</span>`;

            form.department_code.classList.remove('is-invalid');
            form.department_name.classList.remove('is-invalid');

            if (data.message.toLowerCase().includes('code')) {
                form.department_code.classList.add('is-invalid');
            }

            if (data.message.toLowerCase().includes('name')) {
                form.department_name.classList.add('is-invalid');
            }
        }
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

form.department_code.addEventListener('input', () => {
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
                <div class="dept-list-item">
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

let currentEditId = null;

function openEditDept(dept) {
    currentEditId = dept.id;

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

    fetch('department_api.php?action=update', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {

        const msg = document.getElementById('edit-msg');

        if (data.success) {
            msg.innerHTML = `<span class="text-success">${data.message}</span>`;
            setTimeout(() => location.reload(), 600);
        } else {
            msg.innerHTML = `<span class="text-danger">${data.message}</span>`;
        }
    });
});

function deleteDept() {

    if (!confirm('Are you sure you want to delete this department? This cannot be undone.')) {
        return;
    }

    fetch('department_api.php?action=delete', {
        method: 'POST',
        body: new URLSearchParams({ id: currentEditId })
    })
    .then(res => res.json())
    .then(data => {

        if (data.success) {
            alert('Department deleted');
            location.reload();
        } else {
            alert(data.message);
        }
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

</body>
</html>

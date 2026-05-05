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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>

<body>

<?php $currentPage = 'departments'; include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; ?>

    <div class="card card-glass logs-card">
        <div class="card-body d-flex flex-column logs-card-body">

            <!-- HEADER -->
            <div class="deptHeader">

                <!-- LEFT SIDE -->
                <div class="deptHeaderLeft">

                    <!-- SEARCH -->
                    <div class="deptSearchWrapper">
                        <input type="text" id="deptSearch" class="deptSearchInput" placeholder="Search departments...">
                        <i class="bi bi-search searchIcon"></i>
                    </div>

                    <!-- SORT -->
                    <div class="userDropdownWrapper deptSortDropdown">
                        <span class="userEmail dropdown-toggle" id="deptSortToggle">
                            Name (A → Z)
                            <i class="bi bi-chevron-down logArrow"></i>
                        </span>

                        <div class="userDropdownMenu" id="deptSortMenu">
                            <div class="logTypeSection">
                                <a href="#" class="userDropdownItem" data-value="name_asc">Name (A → Z)</a>
                                <a href="#" class="userDropdownItem" data-value="name_desc">Name (Z → A)</a>
                                <a href="#" class="userDropdownItem" data-value="code_asc">Code (A → Z)</a>
                                <a href="#" class="userDropdownItem" data-value="code_desc">Code (Z → A)</a>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="deptSortValue" value="name_asc">

                </div>

                <!-- RIGHT SIDE -->
                <div class="deptHeaderRight">

                    <!-- CREATE -->
                    <button class="createDeptBtn" data-bs-toggle="modal" data-bs-target="#createDeptModal">
                        <i class="bi bi-plus-lg"></i>
                    </button>

                </div>

            </div>

            <!-- GRID -->
            <div class="deptGrid">

                <?php foreach ($departments as $dept): ?>
                    <div class="deptCard">

                        <?php $color = $dept['color'] ?? '#4e73df'; ?>

                        <div class="deptActions">
                            <i class="bi bi-pencil-square editDeptIcon"
                            onclick='openEditDept(<?= json_encode($dept) ?>)'></i>
                        </div>

                        <div class="deptIdentity">
                            <div class="deptIcon"
                                style="background: <?= htmlspecialchars($color) ?>;
                                        color: <?= getContrastColor($color) ?>;">
                                <?= htmlspecialchars($dept['department_code']) ?>
                            </div>

                            <div class="deptName">
                                <?= htmlspecialchars($dept['department_name']) ?>
                            </div>
                        </div>

                        <?php if (!empty($dept['parent_name']) || $dept['child_count'] > 0): ?>
                            <div class="deptPills">

                                <?php if (!empty($dept['parent_name'])): ?>
                                    <button
                                        class="deptParentLink"
                                        onclick="viewParentDepartment(<?= $dept['parent_id'] ?>)">
                                        <i class="bi bi-diagram-3"></i>
                                        Under <?= htmlspecialchars($dept['parent_name']) ?>
                                    </button>
                                <?php endif; ?>

                                <?php if ($dept['child_count'] > 0): ?>
                                    <button
                                        class="deptSubBtn"
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

                        <button class="deptMeta">
                            <i class="bi bi-people"></i>
                            <?= $dept['employee_count'] ?> <?= $dept['employee_count'] != 1 ? 'Employees' : 'Employee' ?>
                        </button>

                    </div>
                <?php endforeach; ?>

            </div>

        </div>
    </div>

    <!-- CREATE MODAL -->
    <div class="modal fade" id="createDeptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content deptModal">

                <div class="modal-header">
                    <h5 class="modal-title">Create Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <form id="createDeptForm">

                        <div class="createDeptLayout">

                            <!-- LEFT: PREVIEW CARD -->
                            <div class="createDeptPreview">
                                <div class="createDeptPreviewIcon" id="previewIcon">
                                </div>
                                <div class="createDeptPreviewName" id="previewName">
                                    Department Name
                                </div>

                                <label class="colorPickWrapper">
                                    <input type="color" class="colorPickInput" name="color" id="colorPicker" value="#4e73df">
                                    Color
                                </label>
                            </div>

                            <!-- RIGHT: FIELDS -->
                            <div class="createDeptFields">

                                <div class="mb-3">
                                    <label class="form-label">Department Code</label>
                                    <input type="text" class="form-control" name="department_code" id="inputCode" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Department Name</label>
                                    <input type="text" class="form-control" name="department_name" id="inputName" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Parent Department (Optional)</label>

                                    <div class="selectWrapper">
                                        <select class="form-control customSelect" name="parent_id">
                                            <option value="">None</option>
                                            <?php foreach ($departmentList as $row): ?>
                                                <option value="<?= $row['id'] ?>">
                                                    <?= htmlspecialchars($row['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <i class="bi bi-chevron-down selectArrow"></i>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 createDeptBtn">
                                    Create Department
                                </button>

                            </div>

                        </div>

                    </form>

                    <div id="deptMsg" class="mt-2 text-center"></div>

                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="editDeptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content deptModal">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <form id="editDeptForm">

                        <input type="hidden" name="id">

                        <div class="mb-3">
                            <label class="form-label">Department Code</label>
                            <input type="text" class="form-control" name="department_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="department_name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department Color</label>
                            <input type="color" class="form-control form-control-color" name="color">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Parent Department</label>
                            <select class="form-control" name="parent_id">
                                <option value="">None</option>
                                <?php foreach ($departmentList as $row): ?>
                                    <option value="<?= $row['id'] ?>">
                                        <?= htmlspecialchars($row['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Save Changes
                        </button>

                        <button type="button" class="btn btn-danger w-100 mt-2" onclick="deleteDept()">
                            Delete Department
                        </button>

                    </form>

                    <div id="editMsg" class="mt-2 text-center"></div>

                </div>

            </div>
        </div>
    </div>

    <!-- PARENT DEPT MODAL -->
    <div class="modal fade" id="parentDeptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content deptModal">

                <div class="modal-header">
                    <h5 class="modal-title">Parent Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" id="parentDeptBody">
                    Loading...
                </div>

            </div>
        </div>
    </div>

    <!-- SUB DEPT MODAL -->
    <div class="modal fade" id="subDeptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content deptModal">

                <div class="modal-header">
                    <h5 class="modal-title" id="subDeptTitle">Sub Departments</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="subDeptList" class="subDeptList"></div>
                </div>

            </div>
        </div>
    </div>

</div><!-- #main-wrapper -->

<!-- JS -->
<script>
const form = document.getElementById('createDeptForm');
const msg  = document.getElementById('deptMsg');
const grid = document.querySelector('.deptGrid');

document.addEventListener('DOMContentLoaded', () => {

    const inputCode   = document.getElementById('inputCode');
    const inputName   = document.getElementById('inputName');
    const colorPicker = document.getElementById('colorPicker');
    const previewIcon = document.getElementById('previewIcon');
    const previewName = document.getElementById('previewName');

    function updatePreview() {
        const code  = inputCode.value.trim()  || '';
        const name  = inputName.value.trim()  || 'Department Name';
        const color = colorPicker.value       || '#4e73df';

        previewIcon.textContent      = code;
        previewIcon.style.background = color;
        previewIcon.style.color      = getContrastColor(color);
        previewName.textContent      = name;
    }

    inputCode.addEventListener('input',   updatePreview);
    inputName.addEventListener('input',   updatePreview);
    colorPicker.addEventListener('input', updatePreview);

    updatePreview();
});

const wrapper = document.querySelector('.selectWrapper');

wrapper.addEventListener('click', function (e) {
    this.classList.toggle('active');
});

document.addEventListener('click', function (e) {
    const wrapper = document.querySelector('.selectWrapper');

    if (!wrapper.contains(e.target)) {
        wrapper.classList.remove('active');
    }
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
            div.className = 'deptCard';

            const color = data.color || '#4e73df';

            div.innerHTML = `
                <div class="deptActions">
                    <i class="bi bi-pencil-square editDeptIcon"
                        onclick='openEditDept(${JSON.stringify(data)})'></i>
                </div>

                <div class="deptIdentity">
                    <div class="deptIcon" style="background:${color}; color:${getContrastColor(color)};">
                        ${data.department_code}
                    </div>
                    <div class="deptName">${data.department_name}</div>
                </div>

                ${data.parent_id && data.parent_name ? `
                    <div class="deptPills">
                        <button class="deptParentLink" onclick="viewParentDepartment(${data.parent_id})">
                            <i class="bi bi-diagram-3"></i>
                            Under ${data.parent_name}
                        </button>
                    </div>
                ` : ''}

                <button class="deptMeta">
                    <i class="bi bi-people"></i>
                    0 Employees
                </button>
            `;

            grid.appendChild(div);

            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('createDeptModal')).hide();
                msg.innerHTML = '';
            }, 800);

        } else {
            msg.innerHTML = `<span class="text-danger">${data.message}</span>`;

            // reset validation first
            form.department_code.classList.remove('is-invalid');
            form.department_name.classList.remove('is-invalid');

            // highlight based on message
            if (data.message.toLowerCase().includes('code')) {
                form.department_code.classList.add('is-invalid');
            }

            if (data.message.toLowerCase().includes('name')) {
                form.department_name.classList.add('is-invalid');
            }
        }
    });
});

/* PARENT DEPT CUSTOM DROPDOWNS */
function toggleDeptDropdown(id) {
    const menu = document.getElementById(id);
    const isOpen = menu.classList.contains('show');
    document.querySelectorAll('.customSelectMenu').forEach(m => m.classList.remove('show'));
    if (!isOpen) menu.classList.add('show');
}

function selectParentDept(modal, value, label) {
    if (modal === 'create') {
        document.getElementById('createParentInput').value = value;
        document.getElementById('createParentLabel').textContent = label;
        document.getElementById('createParentMenu').classList.remove('show');
    } else {
        document.getElementById('editParentInput').value = value;
        document.getElementById('editParentLabel').textContent = label;
        document.getElementById('editParentMenu').classList.remove('show');
    }
}

document.getElementById('createDeptModal').addEventListener('hidden.bs.modal', () => {
    document.getElementById('createParentInput').value = '';
    document.getElementById('createParentLabel').textContent = '-- None (Top Level) --';
});

document.addEventListener('click', e => {
    if (!e.target.closest('.customSelectWrapper')) {
        document.querySelectorAll('.customSelectMenu').forEach(m => m.classList.remove('show'));
    }
});

/* SORT DROPDOWN (log-type style) */
const deptSortWrapper = document.querySelector('.deptSortDropdown');
const deptSortToggle  = document.getElementById('deptSortToggle');
const deptSortMenu    = document.getElementById('deptSortMenu');
const deptSortHidden  = document.getElementById('deptSortValue');

let deptSortOpen = false;

deptSortToggle.addEventListener('mouseenter', () => deptSortMenu.classList.add('show'));
deptSortToggle.addEventListener('mouseleave', () => { if (!deptSortOpen) deptSortMenu.classList.remove('show'); });
deptSortMenu.addEventListener('mouseenter',   () => deptSortMenu.classList.add('show'));
deptSortMenu.addEventListener('mouseleave',   () => { if (!deptSortOpen) deptSortMenu.classList.remove('show'); });

deptSortToggle.addEventListener('click', e => {
    e.stopPropagation();
    deptSortOpen = !deptSortOpen;
    deptSortMenu.classList.toggle('show', deptSortOpen);
});

document.addEventListener('click', e => {
    if (!deptSortWrapper.contains(e.target)) {
        deptSortMenu.classList.remove('show');
        deptSortOpen = false;
    }
});

document.querySelectorAll('#deptSortMenu .userDropdownItem').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        deptSortToggle.innerHTML = `${item.textContent} <i class="bi bi-chevron-down logArrow"></i>`;
        deptSortHidden.value = item.dataset.value;
        deptSortMenu.classList.remove('show');
        deptSortOpen = false;
        applyFilterSort();
    });
});

/* SEARCH + SORT */
const searchInput = document.getElementById('deptSearch');

function applyFilterSort() {
    let cards = Array.from(document.querySelectorAll('.deptCard'));
    const query = searchInput.value.toLowerCase().trim();

    cards.forEach(card => {
        const name = card.querySelector('.deptName')?.textContent.toLowerCase() || '';
        const code = card.querySelector('.deptIcon')?.textContent.toLowerCase() || '';
        card.style.display = (name.includes(query) || code.includes(query)) ? '' : 'none';
    });

    const container = document.querySelector('.deptGrid');

    cards.sort((a, b) => {
        const aName = a.querySelector('.deptName').textContent.trim().toLowerCase();
        const bName = b.querySelector('.deptName').textContent.trim().toLowerCase();
        const aCode = a.querySelector('.deptIcon').textContent.trim().toLowerCase();
        const bCode = b.querySelector('.deptIcon').textContent.trim().toLowerCase();

        switch (deptSortHidden.value) {
            case 'name_asc':  return aName.localeCompare(bName);
            case 'name_desc': return bName.localeCompare(aName);
            case 'code_asc':  return aCode.localeCompare(bCode);
            case 'code_desc': return bCode.localeCompare(aCode);
        }
    });

    cards.forEach(c => container.appendChild(c));
}

searchInput.addEventListener('input', applyFilterSort);
form.department_code.addEventListener('input', () => {
    form.department_code.classList.remove('is-invalid');
});

form.department_name.addEventListener('input', () => {
    form.department_name.classList.remove('is-invalid');
});

// PARENT DEPARTMENT
function viewParentDepartment(parentId) {

    const body = document.getElementById('parentDeptBody');
    body.innerHTML = 'Loading...';

    fetch(`department_api.php?action=get_parent&id=${parentId}`)
        .then(res => res.json())
        .then(data => {

            if (!data) {
                body.innerHTML = '<div class="text-muted">No parent department found</div>';
                return;
            }

            body.innerHTML = `
                <div class="deptListItem">
                    <div class="deptIcon" style="background:${data.color || '#4e73df'}; color:${getContrastColor(data.color || '#4e73df')};">
                        ${data.department_code}
                    </div>
                    <div class="deptName">${data.department_name}</div>
                </div>
            `;
        });

    new bootstrap.Modal(document.getElementById('parentDeptModal')).show();
}

/* SUB DEPARTMENTS */
function viewSubDepartments(id, name) {

    document.getElementById('subDeptTitle').innerText =
        `Sub Departments of ${name}`;

    const list = document.getElementById('subDeptList');
    list.innerHTML = 'Loading...';

    fetch(`department_api.php?action=get_sub&id=${id}`)
        .then(res => res.json())
        .then(data => {

            if (!data.length) {
                list.innerHTML = '<div class="text-muted">No sub departments</div>';
                return;
            }

            list.innerHTML = data.map(d => `
                <div class="deptListItem">
                    <div class="deptIcon" style="background:${d.color || '#4e73df'}; color:${getContrastColor(d.color || '#4e73df')};">
                        ${d.department_code}
                    </div>
                    <div class="deptName">${d.department_name}</div>
                </div>
            `).join('');
        });

    new bootstrap.Modal(document.getElementById('subDeptModal')).show();
}

let currentEditId = null;

function openEditDept(dept) {

    currentEditId = dept.id;

    const form = document.getElementById('editDeptForm');

    form.id.value = dept.id;
    form.department_code.value = dept.department_code;
    form.department_name.value = dept.department_name;
    form.color.value = dept.color || '#4e73df';

    const parentId = String(dept.parent_id || '');
    document.getElementById('editParentInput').value = parentId;
    const matchedItem = [...document.querySelectorAll('#editParentMenu .customSelectItem')]
        .find(el => el.getAttribute('onclick').includes(`'${parentId}'`));
    document.getElementById('editParentLabel').textContent = parentId && matchedItem
        ? matchedItem.textContent.trim()
        : '-- None --';

    new bootstrap.Modal(document.getElementById('editDeptModal')).show();
}

document.getElementById('editDeptForm').addEventListener('submit', function(e) {
    e.preventDefault();

    fetch('department_api.php?action=update', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {

        const msg = document.getElementById('editMsg');

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

    // luminance formula
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b);

    return luminance > 186 ? '#000000' : '#ffffff';
}
</script>

</body>
</html>

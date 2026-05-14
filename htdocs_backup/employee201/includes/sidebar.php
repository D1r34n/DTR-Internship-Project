<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Fresh connection for sidebar only
$sidebar_conn = new mysqli("localhost", "root", '', "e201");
if ($sidebar_conn->connect_error) {
    $pendingCount = 0;
} else {
    $pendingCount = 0;
    $res = $sidebar_conn->query("SELECT COUNT(*) AS cnt FROM employee_requests WHERE status = 'pending'");
    if ($res) {
        $row = $res->fetch_assoc();
        $pendingCount = (int)$row['cnt'];
        $res->free();
    }
    $sidebar_conn->close();
}
?>

<aside class="sidebar" id="sidebar">
    <!-- Hamburger Toggle Button -->
    <div class="toggle-btn" id="toggleBtn">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <div class="logo-container">
        <img src="../assets/img/logo1.png" alt="Company Logo" class="logo">
        <h2>E201 System</h2>
    </div>

    <ul>
        <li>
            <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" data-tooltip="Dashboard">
                <i class="fa-solid fa-house"></i> <span class="link-text">Dashboard</span>
            </a>
        </li>
        <li>
            <a href="manage_employee.php" class="<?= $currentPage === 'manage_employee.php' ? 'active' : '' ?>" data-tooltip="Employees">
                <i class="fa-solid fa-user"></i> <span class="link-text">Employees</span>
            </a>
        </li>
        <li>
            <a href="add_employee.php" class="<?= $currentPage === 'add_employee.php' ? 'active' : '' ?>" data-tooltip="Add Employee">
                <i class="fa-solid fa-user-plus"></i> <span class="link-text">Add Employee</span>
            </a>
        </li>
        <li>
            <a href="employee_requests.php" class="<?= $currentPage === 'employee_requests.php' ? 'active' : '' ?>" data-tooltip="Employee Requests">
                <div class="icon-with-badge">
                    <i class="fa-solid fa-envelope"></i>
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </div>
                <span class="link-text">Employee Requests</span>
            </a>
        </li>
        <li>
            <a href="logs.php" class="<?= $currentPage === 'logs.php' ? 'active' : '' ?>" data-tooltip="Logs">
                <i class="fa-solid fa-file-lines"></i> <span class="link-text">Logs</span>
            </a>
        </li>
        <li>
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['superadmin', 'admin'])): ?>
            <a href="manage_users.php" class="<?= $currentPage === 'manage_users.php' ? 'active' : '' ?>" data-tooltip="Manage Users">
                <i class="fa-solid fa-gear"></i> <span class="link-text">Manage Users</span>
            </a>
        <?php endif; ?>
        </li>
        <li>
            <a href="about.php" class="<?= $currentPage === 'about.php' ? 'active' : '' ?>" data-tooltip="About">
                <i class="fa-solid fa-circle-info"></i> <span class="link-text">About</span>
            </a>
        </li>
        <li>
            <a href="#" id="logoutBtn" data-tooltip="Log Out">
                <i class="fa-solid fa-right-from-bracket"></i> <span class="link-text">Log Out</span>
            </a>
        </li>
    </ul>
</aside>


<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="#e74c3c" viewBox="0 0 24 24" width="48" height="48">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 
                         10 10 10 10-4.48 10-10S17.52 2 
                         12 2zm0 15c-.83 0-1.5-.67-1.5-1.5
                         s.67-1.5 1.5-1.5 1.5.67 
                         1.5 1.5S12.83 17 12 17zm1-4h-2V7h2v6z"/>
            </svg>
        </div>
        <h3>Are you sure you want to log out?</h3>
        <p>You will need to log in again to access the system.</p>
        <div class="modal-buttons">
            <button id="confirmLogout" class="btn-danger">Yes, Logout</button>
            <button id="cancelLogout" class="btn-secondary">Cancel</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../assets/css/style.css">


<script>
// Toggle Sidebar with localStorage persistence
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('toggleBtn');

// Load sidebar state from localStorage
if(localStorage.getItem('sidebarCollapsed') === 'true') {
    sidebar.classList.add('collapsed');
}

toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    // Save state in localStorage
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
});

// Logout Modal
document.getElementById('logoutBtn').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('logoutModal').style.display = 'flex';
});

document.getElementById('cancelLogout').addEventListener('click', function() {
    document.getElementById('logoutModal').style.display = 'none';
});

document.getElementById('confirmLogout').addEventListener('click', function() {
    window.location.href = '../admin/logout.php';
});

window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('logoutModal')) {
        document.getElementById('logoutModal').style.display = 'none';
    }
});

// -----------------------
// TOOLTIP DELAY (only when sidebar is collapsed)
// -----------------------
let tooltip;
let tooltipTimeout;

document.querySelectorAll('.sidebar a').forEach(link => {
    link.addEventListener('mouseenter', (e) => {
        // ✅ Show tooltip only when sidebar is collapsed
        if (!sidebar.classList.contains('collapsed')) return;

        tooltipTimeout = setTimeout(() => {
            const text = link.getAttribute('data-tooltip');
            if (!text) return;

            tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.innerText = text;
            document.body.appendChild(tooltip);

            const rect = link.getBoundingClientRect();
            tooltip.style.top = rect.top + window.scrollY + rect.height / 2 - tooltip.offsetHeight / 2 + 'px';
            tooltip.style.left = rect.right + 10 + 'px';

            requestAnimationFrame(() => {
                tooltip.classList.add('show');
            });
        }, 400); // delay
    });

    link.addEventListener('mouseleave', () => {
        clearTimeout(tooltipTimeout);
        if (tooltip) {
            tooltip.remove();
            tooltip = null;
        }
    });
});
</script>

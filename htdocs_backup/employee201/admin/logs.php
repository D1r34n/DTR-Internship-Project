<?php
session_start();
require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php';
// Handle clear logs request (only for superadmin) BEFORE output
if (isset($_GET['clear']) && $_GET['clear'] === '1' && $_SESSION['admin_role'] === 'superadmin') {
    $conn->query("TRUNCATE TABLE logs");

    // Get admin username
    $admin_id = $_SESSION['admin_id'];
    $admin_username = $_SESSION['admin_username'] ?? 'Unknown';
    $action = "Cleared all system logs (by {$admin_username})";

    // Log the clear action (visible only to superadmin)
    $stmt = $conn->prepare("INSERT INTO logs (admin_id, action, log_time, visible_to) VALUES (?, ?, NOW(), 'superadmin')");
    $stmt->bind_param("is", $admin_id, $action);
    $stmt->execute();
    $stmt->close();

    header("Location: logs.php");
    exit;
}

// ✅ Safe to include after all header() calls
include '../includes/header.php';
include '../includes/sidebar.php';

// Fetch logs depending on role
if ($_SESSION['admin_role'] === 'superadmin') {
    $result = $conn->query("SELECT logs.*, admins.username 
                            FROM logs 
                            JOIN admins ON logs.admin_id = admins.id 
                            ORDER BY log_time DESC");
} else {
    $result = $conn->query("SELECT logs.*, admins.username 
                            FROM logs 
                            JOIN admins ON logs.admin_id = admins.id 
                            WHERE logs.visible_to = 'all'
                            ORDER BY log_time DESC");
}
?>

<style>
/* Main content area */
.main-content {
    margin-left: 210px; /* matches sidebar width */
    padding: 30px;
    background-color: #1e1e2d;
    min-height: 100vh;
    box-sizing: border-box;
    width: calc(100% - 260px);
    transition: margin-left 0.3s ease, width 0.3s ease;
}

/* When sidebar collapsed */
.sidebar.collapsed + .main-content {
    margin-left: 80px;
}

/* Page title and controls */
.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1f2937;
    padding: 20px 25px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.header-container h1 {
    color: #fff;
    font-size: 1.6rem;
    margin: 0;
}

.clear-logs-btn {
    background-color: #ff4d4d;
    color: white;
    padding: 10px 18px;
    font-size: 0.95rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    transition: 0.3s;
}
.clear-logs-btn:hover {
    background-color: #cc0000;
}

/* Logs table container */
.table-container {
    background: #1f2937;
    padding: 20px 25px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    overflow-x: auto;
    animation: fadeIn 0.5s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Table styling */
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

th, td {
    padding: 14px 16px;
    text-align: left;
}

th {
    background-color: #16a085;
    color: #fff;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
}

tr {
    border-bottom: 1px solid #e5e7eb;
}

tr:nth-child(even) {
    background-color: #f9fafb;
}

tr:hover {
    background-color: #1f2937;
    color: #fff;
    transition: background 0.2s ease-in-out;
}

td {
    font-size: 0.95rem;
    color: #333;
}

/* Responsive table */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 15px;
    }
    .header-container {
        flex-direction: column;
        align-items: flex-start;
    }
    table, thead, tbody, th, td, tr {
        display: block;
    }
    thead tr {
        display: none;
    }
    tr {
        margin-bottom: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: #fff;
        padding: 10px;
    }
    td {
        padding: 8px;
        text-align: right;
        position: relative;
        border-bottom: 1px solid #eee;
    }
    td::before {
        content: attr(data-label);
        position: absolute;
        left: 10px;
        font-weight: bold;
        color: #555;
        text-transform: capitalize;
    }
}
</style>

<div class="main-content">
    <div class="header-container">
        <h1><i class="fas fa-file-alt"></i> System Logs</h1>
        <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
            <a href="logs.php?clear=1" class="clear-logs-btn" onclick="return confirm('Are you sure you want to clear all system logs?')">Clear Logs</a>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td data-label="User"><?= htmlspecialchars($row['username']) ?></td>
                        <td data-label="Action"><?= htmlspecialchars($row['action']) ?></td>
                        <td data-label="Timestamp"><?= $row['log_time'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<?php  
session_start(); 
if (!isset($_SESSION['admin_id'])) {     
    header("Location: ../auth/login.php");     
    exit; 
}  

require_once __DIR__ . '/../includes/config.php';  
require_once(__DIR__ . '/../auth/session_check.php');
include '../includes/header.php'; 
include '../includes/sidebar.php';  

// Fetch requests (with reason)
$sql = "
    SELECT r.id, r.employee_code, r.request_type, r.reason, r.status, r.date_requested,
           e.id AS emp_id, e.first_name, e.middle_name,  e.last_name, e.department
    FROM employee_requests r
    JOIN employees e ON r.employee_code = e.employee_code
    ORDER BY r.date_requested DESC
";  
$res = $conn->query($sql); 
?>

<link rel="stylesheet" href="../assets/css/employee_requests.css">  

<div class="main-content">  
    <div class="header-container">  
        <h1><i class="fas fa-envelope-open-text"></i> Employee Requests</h1> 
    </div>  

    <div class="content-container">
        <div class="table-container">  
            <table class="styled-table">  
                <thead>  
                    <tr>  
                        <th>Employee Name</th>  
                        <th>Department</th>  
                        <th>Request Type</th>  
                        <th>Reason</th>  
                        <th>Status</th>  
                        <th>Date Requested</th>  
                        <th>Action</th>  
                    </tr>  
                </thead>  
                <tbody>  
                    <?php if ($res && $res->num_rows > 0): ?>  
                        <?php while ($row = $res->fetch_assoc()): ?>  
                            <tr>    
                                <td class="emp-name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']) ?></td>  
                                <td><?= htmlspecialchars($row['department']) ?></td>  
                                <td><?= $row['request_type'] === 'COE_BASIC' ? 'COE with Basic Salary' : 'COE' ?></td>  
                                <td><?= htmlspecialchars($row['reason'] ?: '-') ?></td>  
                                <td>
                                    <?php  
                                    switch ($row['status']) {  
                                        case 'pending':  
                                            echo '<span class="badge pending">⏳ Pending</span>';  
                                            break;  
                                        case 'approved':  
                                            echo '<span class="badge approved">✅ Approved</span>';  
                                            break;  
                                        case 'declined':  
                                            echo '<span class="badge declined">❌ Declined</span>';  
                                            break;  
                                        default:  
                                            echo '<span class="badge unknown">❓ Unknown</span>';  
                                    }  
                                    ?>  
                                </td>  
                                <td><?= date("Y-m-d H:i", strtotime($row['date_requested'])) ?></td>  
                                <td>  
                                    <?php if ($_SESSION['admin_role'] === 'staff'): ?>  
                                        <!-- Staff can only view, no actions -->
                                        <span class="badge">👀 View Only</span>  

                                    <?php else: ?>  
                                        <?php if ($row['status'] === 'pending'): ?>  

                                            <?php if ($row['request_type'] === 'COE_BASIC' && $_SESSION['admin_role'] !== 'superadmin'): ?>  
                                                <span class="badge declined">🔒 Restricted to Superadmin</span>  
                                            <?php else: ?>  
                                                <!-- Approve -->
                                                <form method="POST" action="cert_of_emp.php" style="display:inline;">
                                                    <input type="hidden" name="id" value="<?= (int)$row['emp_id'] ?>">
                                                    <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                                    <input type="hidden" name="request_type" value="<?= htmlspecialchars($row['request_type']) ?>">
                                                    <button type="submit" class="btn-approve">✅ Approve</button>
                                                </form>

                                                <!-- Decline -->
                                                <form method="POST" action="delete_request.php" style="display:inline;" 
                                                      onsubmit="return confirm('Are you sure you want to decline this request?');">
                                                    <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                                    <input type="hidden" name="action" value="decline">
                                                    <button type="submit" class="btn-decline">❌ Decline</button>
                                                </form>
                                            <?php endif; ?>  

                                        <?php elseif ($row['status'] === 'approved' || $row['status'] === 'declined'): ?>  
                                            <?php if ($row['request_type'] === 'COE_BASIC' && $_SESSION['admin_role'] !== 'superadmin'): ?>  
                                                <span class="badge declined">🔒 Restricted</span>  
                                            <?php else: ?>  
                                                <!-- Delete -->
                                                <form method="POST" action="delete_request.php" style="display:inline;" 
                                                      onsubmit="return confirm('Are you sure you want to permanently delete this request?');">
                                                    <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn-delete">🗑️ Delete</button>
                                                </form>
                                            <?php endif; ?>  
                                        <?php else: ?>  
                                            <span class="badge"><?= ucfirst($row['status']) ?></span>  
                                        <?php endif; ?>  
                                    <?php endif; ?>  
                                </td>
                            </tr>  
                        <?php endwhile; ?>  
                    <?php else: ?>  
                        <tr>  
                            <td colspan="7" class="no-rows">✅ No requests found</td>  
                        </tr>  
                    <?php endif; ?>  
                </tbody>  
            </table>  
        </div>  
    </div> 
</div>  

<?php include '../includes/footer.php'; ?>  

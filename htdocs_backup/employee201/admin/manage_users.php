<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/config.php';

// ✅ Disable MySQLi exception crashing
mysqli_report(MYSQLI_REPORT_OFF);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        // 🔴 DELETE ADMIN
        if (isset($_POST['delete_admin'])) {
            $admin_id = $_POST['admin_id'];

            if ($admin_id == $_SESSION['admin_id']) {
                $error = "You cannot delete your own account.";
            } else {
                $stmt = $conn->prepare("SELECT username FROM admins WHERE id=?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
                $deleted = $admin['username'] ?? 'Unknown';

                $stmt = $conn->prepare("DELETE FROM admins WHERE id=?");
                $stmt->bind_param("i", $admin_id);

                if ($stmt->execute()) {
                    $success = "Admin '$deleted' deleted successfully.";
                    $log = $conn->prepare("INSERT INTO logs (admin_id, action) VALUES (?, ?)");
                    $act = "Deleted user $deleted";
                    $log->bind_param("is", $_SESSION['admin_id'], $act);
                    $log->execute();
                } else {
                    $error = "Failed to delete admin.";
                }
            }
        }

        // 🟢 ADD ADMIN
        if (isset($_POST['add_admin'])) {
            $u   = $_POST['new_username'];
            $e   = $_POST['new_email'];
            $p   = $_POST['new_password'];
            $c   = $_POST['new_confirm'];
            $r   = $_POST['new_role'];

            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email address.";
            } elseif ($p !== $c) {
                $error = "Passwords do not match.";
            } else {
                $hp = password_hash($p, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admins (username, email, password, role, date_added) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssss", $u, $e, $hp, $r);

                if ($stmt->execute()) {
                    $success = "User added successfully.";
                    $log = $conn->prepare("INSERT INTO logs (admin_id, action) VALUES (?, ?)");
                    $act = "Added user $u ($e)";
                    $log->bind_param("is", $_SESSION['admin_id'], $act);
                    $log->execute();
                } else {
                    // Check for duplicate key error
                    if ($conn->errno === 1062) {
                        $error = "That username or email already exists.";
                    } else {
                        $error = "Database error while adding user.";
                    }
                }
            }
        }

        // 🟡 EDIT ADMIN
        if (isset($_POST['edit_admin'])) {
            $id = $_POST['edit_id'];
            $un = $_POST['edit_username'];
            $ur = $_POST['edit_role'];
            $np = $_POST['new_password'] ?? '';
            $cn = $_POST['confirm_password'] ?? '';

            $stmt = $conn->prepare("SELECT username, role FROM admins WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $old_name = $old['username'] ?? '';
            $old_role = $old['role'] ?? '';

            $changes = [];
            if ($un !== $old_name) $changes[] = "Changed username from '$old_name' to '$un'";
            if ($ur !== $old_role) $changes[] = "Changed role from '$old_role' to '$ur'";

            if (!empty($np) || !empty($cn)) {
                if ($np === $cn) {
                    $new_hashed = password_hash($np, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admins SET password=? WHERE id=?");
                    $stmt->bind_param("si", $new_hashed, $id);
                    $stmt->execute();
                    $changes[] = "Updated password";
                } else {
                    $error = "Passwords do not match.";
                }
            }

            if (empty($error) && $changes) {
                $stmt = $conn->prepare("UPDATE admins SET username=?, role=? WHERE id=?");
                $stmt->bind_param("ssi", $un, $ur, $id);
                if ($stmt->execute()) {
                    $success = "Admin updated successfully.";
                    $log = $conn->prepare("INSERT INTO logs (admin_id, action) VALUES (?, ?)");
                    $act = "Updated user $old_name";
                    $log->bind_param("is", $_SESSION['admin_id'], $act);
                    $log->execute();
                } else {
                    if ($conn->errno === 1062) {
                        $error = "That username or email already exists.";
                    } else {
                        $error = "Database error while updating admin.";
                    }
                }
            } elseif (empty($changes)) {
                $error = "No changes made.";
            }
        }

    } catch (mysqli_sql_exception $ex) {
        // 🧱 Catch-all for any unexpected DB issue
        $error = "Database error: " . htmlspecialchars($ex->getMessage());
    }
}

// ✅ Always load users (safe even if error occurred)
$admins = $conn->query("SELECT id, username, email, role, date_added FROM admins ORDER BY date_added DESC");
?>


<link rel="stylesheet" href="../assets/css/style.css">

<div class="main-content">
    <div class="header-container">
        <h1><i class="fas fa-user-cog"></i> Manage Users</h1>
    </div>

    <div class="content-container">
<?php if (!empty($error) || !empty($success)): ?>
<div id="notifModal" class="notif-modal <?= !empty($success) ? 'success' : 'error' ?>">
  <div class="notif-box">
    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <?php if (!empty($success)): ?>
      <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
      <path class="checkmark__check" fill="none" d="M14 27l7 7 16-16"/>
      <?php else: ?>
      <circle class="cross__circle" cx="26" cy="26" r="25" fill="none"/>
      <path class="cross__path cross__path--right" fill="none" d="M16 16l20 20"/>
      <path class="cross__path cross__path--left" fill="none" d="M36 16L16 36"/>
      <?php endif; ?>
    </svg>
    <p><?= htmlspecialchars($success ?? $error) ?></p>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const notif = document.getElementById("notifModal");
  if (notif) {
    notif.classList.add("show");
    setTimeout(() => notif.classList.remove("show"), 1800);
    setTimeout(() => notif.remove(), 2500);
  }
});
</script>
<?php endif; ?>


        <div class="table-card">
            <div class="table-header">
                <h2>User Accounts</h2>
                <button onclick="openAdd()" class="btn-primary"><i class="fa fa-plus"></i> Add User</button>
            </div>

            <div class="table-responsive">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($admins && $admins->num_rows > 0): ?>
                            <?php while ($row = $admins->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                    <td>
                                        <span class="role-badge <?= strtolower($row['role']) ?>">
                                            <?= htmlspecialchars(ucfirst($row['role'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['date_added']) ?></td>
                                    <td class="user-actions">
                                        <button onclick="openEdit('<?= $row['id'] ?>','<?= htmlspecialchars($row['username']) ?>','<?= $row['role'] ?>')" class="btn-sm btn-edit"><i class="fa fa-pen"></i></button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                            <input type="hidden" name="admin_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete_admin" class="btn-sm btn-delete"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="no-data">No users found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="modalAdd" class="modal">
  <div class="box">
    <h2><center>Add User</center></h2>
    <form method="POST" oninput="toggleAddBtn()">
      <input name="new_username" placeholder="Username" required>
      <input type="email" name="new_email" placeholder="Email" required>
      <input type="password" name="new_password" placeholder="Password" required>
      <input type="password" name="new_confirm" placeholder="Confirm Password" required>
      <select name="new_role" required>
        <option value="superadmin">Superadmin</option>
        <option value="admin">Admin</option>
        <option value="staff">Staff</option>
      </select>
      <div class="actions">
        <button type="button" onclick="closeAdd()">Cancel</button>
        <button type="submit" name="add_admin" id="btnAdd" class="disabled" disabled>Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Admin Modal -->
<div id="modalEdit" class="modal">
  <div class="box">
    <h2><center>Edit Admin</center></h2>
    <form method="POST" oninput="toggleEditBtn()">
      <input type="hidden" name="edit_id" id="edit_id">
      <label>Username</label>
      <input name="edit_username" id="edit_username" required>
      <label>Role</label>
      <select name="edit_role" id="edit_role" required>
        <option value="superadmin">Superadmin</option>
        <option value="admin">Admin</option>
        <option value="staff">Staff</option>
      </select>
      <hr>
      <div id="passwordToggleSection">
        <button type="button" onclick="showPasswordFields()" style="width:100%">Change Password</button>
      </div>
      <div id="passwordFields" style="display:none;">
        <label>New Password</label>
        <input type="password" name="new_password" id="new_password">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password">
      </div>
      <div class="actions">
        <button type="button" onclick="closeEdit()">Cancel</button>
        <button type="submit" name="edit_admin" id="btnEdit" class="disabled" disabled>Save</button>
      </div>
    </form>
  </div>
</div>

<script>
let origName='', origRole='';

function openEdit(id, name, role){
  document.getElementById('modalEdit').style.display='flex';
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_username').value=name;
  document.getElementById('edit_role').value=role;
  origName=name; origRole=role;
}

function closeEdit(){ document.getElementById('modalEdit').style.display='none'; }
function openAdd(){ document.getElementById('modalAdd').style.display='flex'; }
function closeAdd(){ document.getElementById('modalAdd').style.display='none'; }

function showPasswordFields(){
  document.getElementById('passwordFields').style.display='block';
  document.getElementById('passwordToggleSection').style.display='none';
}

function toggleAddBtn(){
  const u=document.querySelector('[name="new_username"]').value.trim();
  const e=document.querySelector('[name="new_email"]').value.trim();
  const p=document.querySelector('[name="new_password"]').value;
  const c=document.querySelector('[name="new_confirm"]').value;
  const valid=u&&e&&p&&c&&(p===c);
  const btn=document.getElementById('btnAdd');
  btn.disabled=!valid; btn.classList.toggle('disabled',!valid);
}

function toggleEditBtn(){
  const name=document.getElementById('edit_username').value;
  const role=document.getElementById('edit_role').value;
  const newPass=document.getElementById('new_password').value;
  const confirmPass=document.getElementById('confirm_password').value;
  const changed=(name!==origName)||(role!==origRole)||(newPass&&confirmPass&&newPass===confirmPass);
  const btn=document.getElementById('btnEdit');
  btn.disabled=!changed; btn.classList.toggle('disabled',!changed);
}
</script>

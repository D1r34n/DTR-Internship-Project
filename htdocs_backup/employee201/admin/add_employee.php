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
    /*Layout Fix: Expand like Dashboard*/
    .main-content {
        margin-left: 220px;
        padding: 20px;
        width: calc(100% - 260px);
        transition: margin-left 0.3s ease, width 0.3s ease;
    }

    /* When sidebar is collapsed*/
    .sidebar.collapsed + .main-content {
        margin-left: 80px;
        width: calc(100% - 80px);
    }

    /* Ensure container stretches full width*/
    .main-content .container {
        max-width: 100%;
        width: 100%;
    }
</style>

<div class="main-content">
    <!-- ✅ Unified Header like logs.php -->
    <div class="header-container" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <h1><i class="fas fa-user-plus"></i> Add New Employee</h1>
        <a href="import_employees.php" style="
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            background: linear-gradient(90deg, #059669, #10b981);
            color: #fff;
            transition: all 0.2s;
        " onmouseover="this.style.background='linear-gradient(90deg,#047857,#059669)'; this.style.transform='translateY(-1px)';"
           onmouseout="this.style.background='linear-gradient(90deg,#059669,#10b981)'; this.style.transform='translateY(0)';">
            <i class="fas fa-file-import"></i> Import Employees
        </a>
    </div>

    <!-- ✅ Content Area -->
    <div class="content">
        <form id="employeeForm" method="POST" action="save_employee.php" enctype="multipart/form-data">
            
            <div class="card">
                <h3>Employee Details</h3>
                <div class="form-grid">
                    <div>
                        <label for="employee_code">Employee ID:</label>
                        <input type="text" name="employee_code" id="employee_code" required>
                    </div>
                    <div>
                        <label>First Name:</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div>
                        <label>Middle Name:<strong>(optional)</strong></label>
                        <input type="text" name="middle_name">
                    </div>
                    <div>
                        <label>Last Name:</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div>
                        <label for="gender">Gender:</label>
                        <select name="gender" id="gender" required>
                            <option value="">Select Gender:</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>  
                        </select>
                    </div>                    
                    <div>
                        <label>Position:</label>
                        <input type="text" name="position" required>
                    </div>
                    <div>
                        <label>Birthdate:</label>
                        <input type="date" name="birthdate" required>
                    </div>
                    <div>
                        <label>Email:</label>
                        <input type="email" name="email" required>
                    </div>
                    <div>
                        <label>Phone / Contact No:</label>
                        <input type="text" name="contact_no" required>
                    </div>
                    <div>
                        <label for="department">Department:</label>
                        <select name="department" id="department" required>
                            <option value="">-- Select Department --</option>
                            <option value="Supply Chain">Supply Chain</option>
                            <option value="Sales">Sales</option>
                            <option value="MIS">MIS</option>
                            <option value="Logistics">Logistics</option>
                            <option value="Marketing">Marketing</option>
                            <option value="HR">HR</option>
                            <option value="Finance">Finance</option>
                            <option value="ESG">ESG</option>
                            <option value="CSD">CSD</option>
                            <option value="Contact Center - Acer US">Contact Center - Acer US</option>
                            <option value="Contact Center - AOCC">Contact Center - AOCC</option>
                            <option value="Contact Center - APHI,MY,SG,SocMed">Contact Center - APHI,MY,SG,SocMed</option>
                            <option value="BRC">BRC</option>
                        </select>
                    </div>
                    <div>
                        <label>Emergency Contact Name:</label>
                        <input type="text" name="emergency_name" required>
                    </div>
                    <div>
                        <label>Emergency Contact No:</label>
                        <input type="text" name="emergency_no" required>
                    </div>
                    <div>
                        <label>Date Hired:</label>
                        <input type="date" name="date_hired" required>
                    </div>
                    <div>
                    </div>
                    <div>
                        <label>SSS Number:</label>
                        <input type="text" name="ss_number" required>
                    </div>
                    <div>
                        <label>HMDF Number:</label>
                        <input type="text" name="hmdf_number" required>
                    </div>
                    <div>
                        <label>PhilHealth Number:</label>
                        <input type="text" name="philhealth_number" required>
                    </div>
                    <div>
                        <label>TIN Number:</label>
                        <input type="text" name="tin_number" required>
                    </div>
                    <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin'): ?>
                    <div>   
                        <label for="basic_salary">Basic Salary</label>
                        <input type="number" step="0.01" name="basic_salary" id="basic_salary" placeholder="Enter basic salary">
                    </div>
                    <?php endif; ?>
                    <div>
                        <label>Present Address:</label>
                        <textarea name="present_address" id="present_address" rows="3" required></textarea>

                        <label>Permanent Address:</label>
                        <textarea name="permanent_address" id="permanent_address" rows="3" required></textarea>
                        <label style="margin-top:5px; font-size:0.9rem; color:#94a3b8;">
                            <input type="checkbox" id="sameAddressCheckbox"> Same as Present Address
                        </label>
                    </div>
                    <div>
                        <label for="id_picture">Upload ID Picture</label>
                        <input type="file" name="id_picture" id="id_picture" accept="image/*" >
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>201 Document Uploads</h3>
                <button type="button" class="btn-save" id="addDocumentBtn" style="margin-bottom:15px;">
                    + Add Document
                </button>
                <div id="documentsContainer"></div>
            </div>

            <script>
            const documentsContainer = document.getElementById("documentsContainer");
            const addDocumentBtn = document.getElementById("addDocumentBtn");

            // ✅ Keys must match save_employee.php $documentMap
            const docOptions = {
                "birth_cert": "Birth Certificate",
                "tin": "TIN",
                "sss": "SSS",
                "philhealth": "PhilHealth",
                "pagibig": "Pag-IBIG",
                "resume": "Resume",
                "contract": "Employment Contract",
                "policy": "Signed Policies",
                "medical_clearance": "Medical Clearance",
                "memo": "Memo",
                "incident_report": "Incident Report",
                "disciplinary_action": "Disciplinary Action",
                "commendation": "Commendation",
                "exit_letter": "Exit Letter",
                "interview": "Exit Interview",
                "clearance": "Clearance Form",
                "others": "Others"
            };

            function createDocumentRow() {
                const wrapper = document.createElement("div");
                wrapper.style.display = "flex";
                wrapper.style.alignItems = "center";
                wrapper.style.gap = "10px";
                wrapper.style.marginBottom = "10px";

                // Dropdown
                const select = document.createElement("select");
                select.name = "document_type[]";
                select.required = true;
                select.style.padding = "8px";
                select.style.borderRadius = "6px";
                select.style.border = "1px solid #444";
                select.style.background = "#1e1e2d";
                select.style.color = "#fff";
                select.style.minWidth = "180px";

                Object.entries(docOptions).forEach(([key, label]) => {
                    const option = document.createElement("option");
                    option.value = key;   // ✅ send key (e.g., birth_cert)
                    option.textContent = label; // readable
                    select.appendChild(option);
                });

                // File input
                const fileInput = document.createElement("input");
                fileInput.type = "file";
                fileInput.name = "document_file[]";
                fileInput.required = true;
                fileInput.style.flex = "1";
                fileInput.style.minWidth = "250px";

                // Remove button
                const removeBtn = document.createElement("button");
                removeBtn.type = "button";
                removeBtn.textContent = "Remove";
                removeBtn.style.background = "#dc3545";
                removeBtn.style.color = "#fff";
                removeBtn.style.border = "none";
                removeBtn.style.padding = "6px 10px";
                removeBtn.style.borderRadius = "4px";
                removeBtn.style.cursor = "pointer";
                removeBtn.addEventListener("click", () => wrapper.remove());

                wrapper.appendChild(select);
                wrapper.appendChild(fileInput);
                wrapper.appendChild(removeBtn);

                documentsContainer.appendChild(wrapper);
            }

            addDocumentBtn.addEventListener("click", createDocumentRow);

            // ✅ Same as Present Address Feature
            const sameAddressCheckbox = document.getElementById("sameAddressCheckbox");
            const presentAddress = document.getElementById("present_address");
            const permanentAddress = document.getElementById("permanent_address");

            sameAddressCheckbox.addEventListener("change", () => {
                if (sameAddressCheckbox.checked) {
                    permanentAddress.value = presentAddress.value;
                    permanentAddress.setAttribute("readonly", "true");
                } else {
                    permanentAddress.removeAttribute("readonly");
                }
            });

            presentAddress.addEventListener("input", () => {
                if (sameAddressCheckbox.checked) {
                    permanentAddress.value = presentAddress.value;
                }
            });
            </script>


            <button type="button" class="btn-save" id="openModalBtn">Save Employee</button>
        </form>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="modal">
    <div class="modal-content">
        <h3>Confirm Employee Details</h3>
        <div class="modal-details" id="employeeDetailsPreview"></div>
        <p>Do you want to add this employee with the above details?</p>
        <div class="modal-buttons">
            <button class="btn-cancel" id="cancelBtn">Cancel</button>
            <button class="btn-confirm" id="confirmBtn">Confirm</button>
        </div>
    </div>
</div>

<!-- ✅ Credentials Modal -->
<?php if (isset($_SESSION['loginCredentials'])): ?>
<div id="credentialsModal" class="modal" style="display:block;">
    <div class="modal-content">
        <h3>Employee Login Credentials</h3>
        <div class="modal-details">
            <p><strong>Username:</strong> <?= $_SESSION['loginCredentials']['username'] ?></p>
            <p><strong>Password:</strong> <?= $_SESSION['loginCredentials']['password'] ?></p>
        </div>
        <div class="modal-buttons">
            <button class="btn-confirm" onclick="document.getElementById('credentialsModal').style.display='none';">OK</button>
        </div>
    </div>
</div>
<?php unset($_SESSION['loginCredentials']); endif; ?>

<script>
const modal = document.getElementById("confirmationModal");
const openModalBtn = document.getElementById("openModalBtn");
const cancelBtn = document.getElementById("cancelBtn");
const confirmBtn = document.getElementById("confirmBtn");
const form = document.getElementById("employeeForm");
const detailsPreview = document.getElementById("employeeDetailsPreview");

// === Real-time Custom Validation Message ===
document.querySelectorAll("input[required], select[required], textarea[required]").forEach(field => {
    field.addEventListener("invalid", () => {
        field.setCustomValidity("Please fill out this field");
    });
    field.addEventListener("input", () => {
        field.setCustomValidity("");
    });
});

// === Show Confirmation Modal Only If Valid ===
openModalBtn.addEventListener("click", (e) => {
    if (!form.checkValidity()) {
        form.reportValidity(); // shows "Please fill out this field"
        return;
    }

    // ✅ If valid, show confirmation modal
    const formData = new FormData(form);
    let html = "<ul>";
    formData.forEach((value, key) => {
        if (key !== "id_picture" && key !== "document_file[]") {
            if (key === "basic_salary" && !value) return;
            html += `<li><strong>${key}:</strong> ${value}</li>`;
        }
    });
    html += "</ul>";
    detailsPreview.innerHTML = html;
    modal.style.display = "block";
});

// === Cancel button hides modal ===
cancelBtn.addEventListener("click", () => {
    modal.style.display = "none";
});

// === Confirm button submits form ===
confirmBtn.addEventListener("click", () => {
    form.submit();
});

// === Close modal when clicking outside ===
window.addEventListener("click", (e) => {
    if (e.target === modal) {
        modal.style.display = "none";
    }
});
</script>


<?php include '../includes/footer.php'; ?>
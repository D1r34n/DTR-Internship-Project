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

<style>
/* Main content area */
.main-content {
    margin-left: 210px;
    padding: 25px;
    background-color: #1e1e2d;
    min-height: 100vh;
    box-sizing: border-box;
    width: calc(100% - 260px);
    transition: margin-left 0.3s ease, width 0.3s ease;
}

/* When sidebar collapsed */
.sidebar.collapsed ~ .main-content {
    margin-left: 50px;
    width: calc(100% - 50px);
}

/* Header container (same as logs.php) */
.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1f2937;
    padding: 20px 25px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 25px;    
    flex-wrap: wrap;
}
.header-container h1 {
    color: #fff;
    font-size: 1.6rem;
    margin: 0;
}

/* About box styling */
.about-box {
    background: #1f2937;
    padding: 25px;
    border-radius: 12px;
    margin-top: 15px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    animation: fadeIn 0.5s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.about-box img {
    width: 100px;
    margin-bottom: 15px;
    border-radius: 8px;
}

.about-box h3 {
    color: #16a085;
    margin-top: 20px;
}

.about-box p, 
.about-box ul {
    line-height: 1.7;
    color: #f8fafc;
    text-align: left;
}

.about-box ul {
    margin-left: 20px;
    list-style: disc;
}

.section-divider {
    border-top: 1px solid #374151;
    margin: 30px 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 15px;
    }
    .about-box {
        padding: 15px;
        text-align: left;
    }
    .about-box img {
        display: block;
        margin: 0 auto 15px auto;
    }
    .about-box h3 {
        text-align: center;
    }
    .about-box ul {
        margin-left: 15px;
    }
}
</style>

<div class="main-content">
    <div class="header-container">
        <h1><i class="fas fa-info-circle"></i> About This System</h1>
    </div>

    <div class="about-box">
        <!-- Logo -->
        <img src="../assets/img/logo1.png" alt="HSN Philippines Company Logo">

        <h3>📌 E201 File Management System</h3>
        <p>
            The <strong>201 File Management System</strong> is a secure and efficient platform 
            for managing employee records. It centralizes all employee data including personal details, 
            employment status, certificates, and requests in one place—making HR operations faster, 
            more transparent, and compliant with legal requirements.
        </p>

        <div class="section-divider"></div>

<h3>✨ Key Features</h3>
<ul>
    <li>🔐 Secure, role-based access for Super Admins, Admins, and Staffs</li>
    <li>📁 Organized Employee 201 files with categorized uploads (personal, employment, disciplinary, exit)</li>
    <li>🆕 Clear separation between existing documents and new uploads</li>
    <li>📄 Generate official documents like Certificates of Employment (COE)</li>
    <li>📊 Modern dashboard with analytics, logs, and employee tracking</li>
    <li>💬 Interactive success and error modals with smooth animations</li>
    <li>🛡️ Improved password reset flow with token validation and clean UI</li>
    <li>⚙️ Smart error handling for duplicate entries (no more PHP crashes)</li>
    <li>📱 Fully responsive, dark-mode–friendly interface</li>
    <li>🚀 Fast and intuitive navigation via the modern sidebar dashboard</li>
</ul>


<h3>🤖 AI Chatbot Assistant (AIRA)</h3>
<p>
    This system includes an integrated AI-powered chatbot called <strong>AIRA</strong>.  
    AIRA helps administrators quickly navigate the system, answer HR-related queries, and execute commands.  
    It supports two AI providers:
</p>

<div style="display: flex; align-items: center; gap: 40px; flex-wrap: wrap; margin: 20px 0;">
    <!-- Gemini AI -->
    <div style="text-align: center;">
        <img src="../assets/img/gemini.jpg" alt="Gemini AI Logo" style="width: 120px; height: auto; margin-bottom: 10px;">
        <p style="color: #f8fafc; margin: 0;">
            ☁️ <strong>Gemini AI (Cloud-based)</strong><br>
            Advanced reasoning & cloud intelligence
        </p>
    </div>

    <!-- Ollama AI -->
    <div style="text-align: center;">
        <img src="../assets/img/ollama.webp" alt="Ollama AI Logo" style="width: 120px; height: auto; margin-bottom: 10px;">
        <p style="color: #f8fafc; margin: 0;">
            💻 <strong>Ollama AI (Local)</strong><br>
            Offline private AI assistance
        </p>
    </div>
</div>


        <h3>⚙️ Rule-Based Commands</h3>
        <p>AIRA supports direct system commands in addition to AI conversation:</p>

        <ul>
            <li><code>system info</code> → Displays system info & AI provider</li>
            <li><code>show my role</code> → Shows your current user role</li>

            <li><code>count employees</code> → Total number of employees</li>
            <li><code>count male</code> → Number of male employees</li>
            <li><code>count female</code> → Number of female employees</li>
            <li><code>count admins</code> → Number of admin accounts</li>
            <li><code>count by department</code> → Employee count by department</li>

            <li><code>hired this month</code> → Employees hired this month</li>
            <li><code>terminated this month</code> → Employees terminated this month</li>

            <li><code>get employee {employee_code}</code> → Get details of an employee by employee code</li>
            <li><code>search employee {name}</code> → Search employee by name</li>
            <li><code>list employees</code> → Shows first 5 employees</li>
            <li><code>latest employees</code> → Shows 5 most recently hired employees</li>

            <li><code>birthday today</code> → Employees with birthdays today</li>
            <li><code>birthdays this month</code> → Employees with birthdays this month</li>

            <li><code>latest admins</code> → Shows 5 latest created admin accounts</li>

            <li><code>show latest logs</code> → Shows last 5 activity logs</li>
            <li><code>recent activities</code> → Same as above</li>

            <li><code>open dashboard</code> → Redirect to Dashboard</li>
            <li><code>open manage</code> → Redirect to Manage Employees</li>
            <li><code>open add</code> → Redirect to Add Employee</li>

            <li><code>logout</code> → Logs out of the system</li>
            <li><code>clear chat</code> → Clears chatbot history</li>
            <li><code>help</code> or <code>commands</code> → Show available commands</li>

            <li><code>hi</code>, <code>hello</code>, <code>hey</code> → Greeting</li>
            <li><code>thank you</code> or <code>thanks</code> → Appreciation response</li>
        </ul>


        <div class="section-divider"></div>

        <h3>🔒 Security & Compliance</h3>
        <p>
            This system follows the <strong>Data Privacy Act of 2012 (RA 10173)</strong>.  
            All employee information is treated with strict confidentiality, encrypted, and 
            accessible only to authorized administrators. Regular activity logs are recorded to 
            ensure accountability and transparency.
        </p>

        <div class="section-divider"></div>

        <h3>👨‍💻 Development Team</h3>
        <p><strong>Anthony Revil</strong> - Senior Developer / Analyst / IT Manager</p>
        <p><strong>John Rey Cailo</strong> – System Developer</p>

        <div class="section-divider"></div>

        <h3>🚀 Future Enhancements</h3>
        <ul>
            <li>🔔 Email & SMS notifications for requests and approvals</li>
            <li>📥 Employee self-service portal for submitting requests</li>
            <li>📑 Advanced reporting & analytics dashboard</li>
            <li>🌐 Cloud integration for backup and remote access</li>
            <li>🤖 AI-powered predictive analytics for HR insights</li>
        </ul>

        <p style="margin-top: 30px; text-align: center; font-size: 14px; color: #9ca3af;">
            © <?= date("Y") ?> HSN Philippines – All Rights Reserved
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>  

<?php
require_once(__DIR__ . '/../auth/session_check.php');
include $_SERVER['DOCUMENT_ROOT'] . '/employee201/includes/db.php';


$sql = "CREATE TABLE IF NOT EXISTS incident_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    incident_datetime DATETIME NOT NULL,
    nature VARCHAR(50),
    attendance_detail VARCHAR(100),
    conduct_detail VARCHAR(255),
    minutes_value INT DEFAULT 0,
    dates_in_month VARCHAR(255),
    evidence LONGTEXT,
    action_taken LONGTEXT,
    recommendations LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Table 'incident_reports' created successfully!";
} else {
    echo "❌ Error creating table: " . $conn->error;
}

$conn->close();
?>
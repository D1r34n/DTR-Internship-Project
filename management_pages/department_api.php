<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

switch ($action) {

/* =========================================
   CREATE DEPARTMENT
========================================= */
case 'create':
    $code      = strtoupper(trim($_POST['department_code'] ?? ''));
    $name      = trim($_POST['department_name'] ?? '');
    $parent_id = $_POST['parent_id'] ?: null;
    $color     = $_POST['color'] ?? '#4e73df';

    if ($code === '' || $name === '') {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit();
    }

    $parent_name = null;
    if ($parent_id) {
        $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
        $stmt->execute([$parent_id]);
        $parent_name = $stmt->fetchColumn();
        if (!$parent_name) {
            echo json_encode(['success' => false, 'message' => 'Invalid parent department']);
            exit();
        }
    }

    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_code = ? OR department_name = ?");
        $check->execute([$code, $name]);
        if ($check->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Department already exists']);
            exit();
        }

        $pdo->prepare("INSERT INTO departments (department_code, department_name, parent_id, color) VALUES (?, ?, ?, ?)")
            ->execute([$code, $name, $parent_id, $color]);

        $pdo->prepare("INSERT INTO logs (employee_id, log_type, log_time, edit_reason, edit_requested_by) VALUES (?, 'ADD_DEPARTMENT', NOW(), ?, ?)")
            ->execute([
                $_SESSION['user_id'] ?? null,
                json_encode(['department_name' => $name, 'department_code' => $code, 'parent_name' => $parent_name]),
                $_SESSION['user_id'] ?? null,
            ]);

        echo json_encode([
            'success'         => true,
            'message'         => 'Department created',
            'department_code' => $code,
            'department_name' => $name,
            'parent_id'       => $parent_id,
            'parent_name'     => $parent_name,
            'color'           => $color,
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->errorInfo[1] == 1062
                ? 'Duplicate department (code or name already exists)'
                : 'Server error creating department',
        ]);
    }
    break;

/* =========================================
   LIST DEPARTMENTS
========================================= */
case 'list':
    $stmt = $pdo->query("SELECT id, department_code, department_name FROM departments ORDER BY department_name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;

/* =========================================
   GET PARENT DEPARTMENT
========================================= */
case 'get_parent':
    $id   = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT id, department_code, department_name FROM departments WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    break;

/* =========================================
   GET SUB DEPARTMENTS
========================================= */
case 'get_sub':
    $id   = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT id, department_code, department_name, color FROM departments WHERE parent_id = ? ORDER BY department_name ASC");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;

/* =========================================
   UPDATE DEPARTMENT
========================================= */
case 'update':
    $deptId      = (int)$_POST['id'];
    $newCode     = strtoupper(trim($_POST['department_code'] ?? ''));
    $newName     = trim($_POST['department_name'] ?? '');
    $newColor    = $_POST['color']     ?? null;
    $newParentId = $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;

    $before = $pdo->prepare("
        SELECT d.department_code, d.department_name, p.department_name AS parent_name
        FROM departments d
        LEFT JOIN departments p ON p.id = d.parent_id
        WHERE d.id = ?
    ");
    $before->execute([$deptId]);
    $old = $before->fetch(PDO::FETCH_ASSOC) ?: [];

    $newParentName = null;
    if ($newParentId) {
        $s = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
        $s->execute([$newParentId]);
        $newParentName = $s->fetchColumn() ?: null;
    }

    $pdo->prepare("UPDATE departments SET department_code = ?, department_name = ?, color = ?, parent_id = ? WHERE id = ?")
        ->execute([$newCode, $newName, $newColor, $newParentId, $deptId]);

    $diff = [];
    if (($old['department_name'] ?? '') !== $newName)
        $diff['Department'] = ['before' => $old['department_name'] ?? '—', 'after' => $newName];
    if (($old['department_code'] ?? '') !== $newCode)
        $diff['Code']       = ['before' => $old['department_code'] ?? '—', 'after' => $newCode];
    if (($old['parent_name'] ?? null) !== $newParentName)
        $diff['Parent']     = ['before' => $old['parent_name'] ?? '—', 'after' => $newParentName ?? '—'];

    if (!empty($diff)) {
        $pdo->prepare("INSERT INTO logs (employee_id, log_type, log_time, edit_reason, edit_requested_by) VALUES (?, 'EDIT_DEPARTMENT', NOW(), ?, ?)")
            ->execute([$_SESSION['user_id'] ?? null, json_encode($diff), $_SESSION['user_id'] ?? null]);
    }

    echo json_encode(['success' => true, 'message' => 'Updated']);
    break;

/* =========================================
   DELETE DEPARTMENT
========================================= */
case 'delete':
    $deptId = (int)$_POST['id'];

    $snap = $pdo->prepare("
        SELECT d.department_code, d.department_name, p.department_name AS parent_name
        FROM departments d
        LEFT JOIN departments p ON p.id = d.parent_id
        WHERE d.id = ?
    ");
    $snap->execute([$deptId]);
    $info = $snap->fetch(PDO::FETCH_ASSOC) ?: [];

    $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$deptId]);

    $pdo->prepare("INSERT INTO logs (employee_id, log_type, log_time, edit_reason, edit_requested_by) VALUES (?, 'DELETE_DEPARTMENT', NOW(), ?, ?)")
        ->execute([
            $_SESSION['user_id'] ?? null,
            json_encode([
                'department_name' => $info['department_name'] ?? '—',
                'department_code' => $info['department_code'] ?? '—',
                'parent_name'     => $info['parent_name']     ?? null,
            ]),
            $_SESSION['user_id'] ?? null,
        ]);

    echo json_encode(['success' => true, 'message' => 'Deleted']);
    break;

/* =========================================
   INVALID ACTION
========================================= */
default:
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

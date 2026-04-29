<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

/* =========================================
   CREATE DEPARTMENT
========================================= */
if ($action === 'create') {

    $code = trim($_POST['department_code'] ?? '');
    $name = trim($_POST['department_name'] ?? '');
    $parent_id = $_POST['parent_id'] ?: null;
    $color = $_POST['color'] ?? '#4e73df';

    if ($code === '' || $name === '') {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit();
    }

    // validate parent
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

        // OPTIONAL PRE-CHECK (nice UX)
        $check = $pdo->prepare("
            SELECT COUNT(*) 
            FROM departments 
            WHERE department_code = ? OR department_name = ?
        ");
        $check->execute([$code, $name]);

        if ($check->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Department already exists'
            ]);
            exit();
        }

        // INSERT
        $stmt = $pdo->prepare("
            INSERT INTO departments (department_code, department_name, parent_id, color)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$code, $name, $parent_id, $color]);

        echo json_encode([
            'success' => true,
            'message' => 'Department created',
            'department_code' => $code,
            'department_name' => $name,
            'parent_id' => $parent_id,
            'parent_name' => $parent_name,
            'color' => $color
        ]);

    } catch (PDOException $e) {

        // MySQL duplicate key error
        if ($e->errorInfo[1] == 1062) {
            echo json_encode([
                'success' => false,
                'message' => 'Duplicate department (code or name already exists)'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Server error creating department'
            ]);
        }
    }

    exit();
}

// List all departments
if ($action === 'list') {
    $stmt = $pdo->query("SELECT id, department_code, department_name FROM departments ORDER BY department_name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit();
}

// Get Parent Departments
if ($action === 'get_parent') {

    $id = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT id, department_code, department_name
        FROM departments
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit();
}

/* =========================================
   GET SUB DEPARTMENTS
========================================= */
if ($action === 'get_sub') {

    $id = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT id, department_code, department_name
        FROM departments
        WHERE parent_id = ?
        ORDER BY department_name ASC
    ");

    $stmt->execute([$id]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit();
}

//Update Department
if ($action === 'update') {

    $stmt = $pdo->prepare("
        UPDATE departments
        SET department_code = ?, department_name = ?, color = ?, parent_id = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['department_code'],
        $_POST['department_name'],
        $_POST['color'],
        $_POST['parent_id'] ?: null,
        $_POST['id']
    ]);

    echo json_encode(['success' => true, 'message' => 'Updated']);
    exit;
}

// Delete Department
if ($action === 'delete') {

    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->execute([$_POST['id']]);

    echo json_encode(['success' => true, 'message' => 'Deleted']);
    exit;
}

/* =========================================
   INVALID ACTION
========================================= */
echo json_encode(['success' => false, 'message' => 'Invalid action']);
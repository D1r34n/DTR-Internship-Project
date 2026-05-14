php
<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once '../includes/auth.php';
require_once(__DIR__ . '/../auth/session_check.php');
if (!isAdminLoggedIn()) {
    header("Location: ../auth/login.php");
    exit();
}

/* -------------------- AJAX: LIST MODAL DATA (new / terminated) -------------------- */
if (isset($_GET['list'])) {
    header('Content-Type: application/json');

    $type = $_GET['list']; // 'new' or 'terminated'
    $dept = isset($_GET['dept']) ? trim($_GET['dept']) : null;
    $age  = isset($_GET['age'])  ? trim($_GET['age'])  : null;

    $deptCond = '';
    if ($dept !== null && $dept !== '') {
        if ($dept === 'Unassigned') {
            $deptCond = " AND (department IS NULL OR department = '') ";
        } else {
            $deptCond = " AND department = '" . $conn->real_escape_string($dept) . "' ";
        }
    }

    $ageCond = '';
    if ($age) {
        switch ($age) {
            case '20-29 yrs': $ageCond = " AND TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 20 AND 29 "; break;
            case '30-39 yrs': $ageCond = " AND TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 30 AND 39 "; break;
            case '40-49 yrs': $ageCond = " AND TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 40 AND 49 "; break;
            case '50-59 yrs': $ageCond = " AND TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 50 AND 59 "; break;
            case '60+ yrs':   $ageCond = " AND TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 60 "; break;
        }
        $ageCond .= " AND birthdate IS NOT NULL ";
    }

    $rows = [];
    if ($type === 'new') {
        $sql = "
            SELECT first_name, last_name, date_hired, department, position
            FROM employees
            WHERE MONTH(date_hired) = MONTH(CURRENT_DATE())
              AND YEAR(date_hired)  = YEAR(CURRENT_DATE())
              $deptCond
              $ageCond
            ORDER BY date_hired DESC, last_name ASC, first_name ASC
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = [
                    'name'       => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    'date'       => $r['date_hired'] ? date('Y-m-d', strtotime($r['date_hired'])) : '',
                    'department' => $r['department'] ?? '',
                    'position'   => $r['position'] ?? '',
                ];
            }
        }
    } elseif ($type === 'terminated') {
        $sql = "
            SELECT first_name, last_name, date_resigned, department, position, date_of_separation
            FROM employees
            WHERE employment_status IN ('Retired','Resigned','Fired')
              AND MONTH(date_resigned) = MONTH(CURRENT_DATE())
              AND YEAR(date_resigned)  = YEAR(CURRENT_DATE())
              $deptCond
              $ageCond
            ORDER BY date_resigned DESC, last_name ASC, first_name ASC
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = [
                    'name'       => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    'date'       => $r['date_resigned'] ? date('Y-m-d', strtotime($r['date_resigned'])) : '',
                    'department' => $r['department'] ?? '',
                    'position'   => $r['position'] ?? '',
                    'dateofseparation' => $r['date_of_separation'] ? date('Y-m-d', strtotime($r['date_of_separation'])) : '',
                ];
            }
        }
    } else {
        echo json_encode(['error' => 'Invalid list type']);
        exit();
    }

    echo json_encode(['type' => $type, 'dept' => $dept, 'age' => $age, 'rows' => $rows]);
    exit();
}
/* -------------------- END AJAX LIST -------------------- */

/* ---------- AJAX FILTER: DEPARTMENT ---------- */
if (isset($_GET['dept'])) {
    header('Content-Type: application/json');
    $dept = trim($_GET['dept']);
    $isUnassigned = ($dept === 'Unassigned');
    $deptCond = $isUnassigned ? "(department IS NULL OR department = '')" : "department = '" . $conn->real_escape_string($dept) . "'";

    // T-card metrics for the dept (active now)
    $totalDept = $conn->query("
        SELECT COUNT(*) AS total
        FROM employees
        WHERE $deptCond
          AND date_hired <= NOW()
          AND (date_resigned IS NULL OR date_resigned > NOW())
    ")->fetch_assoc()['total'] ?? 0;

    $newJoinedDept = $conn->query("
        SELECT COUNT(*) AS total
        FROM employees
        WHERE $deptCond
          AND MONTH(date_hired) = MONTH(CURRENT_DATE())
          AND YEAR(date_hired)  = YEAR(CURRENT_DATE())
    ")->fetch_assoc()['total'] ?? 0;

    // OFFBOARDED = total historical, not just this month
    $terminatedDept = $conn->query("
        SELECT COUNT(*) AS total
        FROM employees
        WHERE $deptCond
          AND employment_status IN ('Retired','Resigned','Fired')
    ")->fetch_assoc()['total'] ?? 0;

    // Growth last 5 months (active as of month end)
    $growthLabels = []; $growthCounts = [];
    for ($i = 4; $i >= 0; $i--) {
        $monthEnd = new DateTime("last day of -$i month");
        $monthEnd->setTime(23, 59, 59);
        $endStr = $conn->real_escape_string($monthEnd->format('Y-m-d H:i:s'));
        $growthLabels[] = $monthEnd->format('M Y');
        $count = $conn->query("
            SELECT COUNT(*) AS total
            FROM employees
            WHERE $deptCond
              AND date_hired <= '$endStr'
              AND (date_resigned IS NULL OR date_resigned > '$endStr')
        ")->fetch_assoc()['total'] ?? 0;
        $growthCounts[] = (int)$count;
    }

    // Retention buckets (active only)
    $r = $conn->query("
        SELECT
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) < 36 THEN 1 ELSE 0 END)  AS b0_2,
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE())>=36 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE())<60  THEN 1 ELSE 0 END) AS b3_5,
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE())>=60 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE())<96  THEN 1 ELSE 0 END) AS b5_7,
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE())>=96 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE())<120 THEN 1 ELSE 0 END) AS b8_10,
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE())>=120 THEN 1 ELSE 0 END) AS b10p
        FROM employees
        WHERE date_hired IS NOT NULL
          AND (date_resigned IS NULL OR date_resigned > CURDATE())
          AND $deptCond
    ")->fetch_assoc();
    $retCounts = [
        (int)($r['b0_2'] ?? 0),
        (int)($r['b3_5'] ?? 0),
        (int)($r['b5_7'] ?? 0),
        (int)($r['b8_10'] ?? 0),
        (int)($r['b10p'] ?? 0)
    ];

    // Age distribution + avg age (active only)
    $a = $conn->query("
        SELECT
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 20 AND 29 THEN 1 ELSE 0 END) AS a20_29,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 30 AND 39 THEN 1 ELSE 0 END) AS a30_39,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 40 AND 49 THEN 1 ELSE 0 END) AS a40_49,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 50 AND 59 THEN 1 ELSE 0 END) AS a50_59,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 60 THEN 1 ELSE 0 END) AS a60p,
          AVG(TIMESTAMPDIFF(YEAR, birthdate, CURDATE())) AS avg_age
        FROM employees
        WHERE birthdate IS NOT NULL
          AND date_hired <= CURDATE()
          AND (date_resigned IS NULL OR date_resigned > CURDATE())
          AND $deptCond
    ")->fetch_assoc();
    $ageCounts = [
        (int)($a['a20_29'] ?? 0),
        (int)($a['a30_39'] ?? 0),
        (int)($a['a40_49'] ?? 0),
        (int)($a['a50_59'] ?? 0),
        (int)($a['a60p'] ?? 0),
    ];
    $avgAge = isset($a['avg_age']) ? round((float)$a['avg_age'], 1) : 0;

    // Terminated distribution overall for that dept
    $t = $conn->query("
        SELECT
          SUM(CASE WHEN employment_status='Retired' THEN 1 ELSE 0 END) AS retired,
          SUM(CASE WHEN employment_status='Resigned' THEN 1 ELSE 0 END) AS resigned,
          SUM(CASE WHEN employment_status='Fired'   THEN 1 ELSE 0 END) AS fired
        FROM employees
        WHERE employment_status IN ('Retired','Resigned','Fired')
          AND $deptCond
    ")->fetch_assoc();
    $termCounts = [
        (int)($t['retired'] ?? 0),
        (int)($t['resigned'] ?? 0),
        (int)($t['fired'] ?? 0),
    ];

    echo json_encode([
        'dept'           => $dept,
        'tcard'          => ['total'=>$totalDept, 'new'=>$newJoinedDept, 'terminated'=>$terminatedDept],
        'growth'         => ['labels'=>$growthLabels, 'counts'=>$growthCounts],
        'retention'      => ['counts'=>$retCounts],
        'age'            => ['counts'=>$ageCounts, 'avg'=>$avgAge],
        'terminatedDist' => ['counts'=>$termCounts],
    ]);
    exit();
}

/* ---------- AJAX FILTER: AGE RANGE ---------- */
if (isset($_GET['age'])) {
    header('Content-Type: application/json');
    $age = trim($_GET['age']);
    $ageCond = '';
    switch ($age) {
        case '20-29 yrs': $ageCond = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 20 AND 29"; break;
        case '30-39 yrs': $ageCond = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 30 AND 39"; break;
        case '40-49 yrs': $ageCond = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 40 AND 49"; break;
        case '50-59 yrs': $ageCond = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 50 AND 59"; break;
        case '60+ yrs':   $ageCond = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 60"; break;
        default: echo json_encode(['error'=>'Invalid age range']); exit();
    }
    $ageCond .= " AND birthdate IS NOT NULL ";

    // T-card metrics (active now)
    $totalAge = $conn->query("
        SELECT COUNT(*) AS total
        FROM employees
        WHERE ($ageCond)
          AND date_hired <= NOW()
          AND (date_resigned IS NULL OR date_resigned > NOW())
    ")->fetch_assoc()['total'] ?? 0;

    $newJoinedAge = $conn->query("
        SELECT COUNT(*) AS total
        FROM employees
        WHERE ($ageCond)
          AND MONTH(date_hired) = MONTH(CURRENT_DATE())
          AND YEAR(date_hired)  = YEAR(CURRENT_DATE())
    ")->fetch_assoc()['total'] ?? 0;

    // OFFBOARDED = total historical, not just this month
    $terminatedAge = $conn->query("
        SELECT COUNT(*) AS total
        FROM employees
        WHERE ($ageCond)
          AND employment_status IN ('Retired','Resigned','Fired')
    ")->fetch_assoc()['total'] ?? 0;

    // Growth last 5 months (active as of month end)
    $growthLabels = []; $growthCounts = [];
    for ($i = 4; $i >= 0; $i--) {
        $monthEnd = new DateTime("last day of -$i month");
        $monthEnd->setTime(23, 59, 59);
        $endStr = $conn->real_escape_string($monthEnd->format('Y-m-d H:i:s'));
        $growthLabels[] = $monthEnd->format('M Y');
        $count = $conn->query("
            SELECT COUNT(*) AS total
            FROM employees
            WHERE ($ageCond)
              AND date_hired <= '$endStr'
              AND (date_resigned IS NULL OR date_resigned > '$endStr')
        ")->fetch_assoc()['total'] ?? 0;
        $growthCounts[] = (int)$count;
    }

    // Retention buckets (active only)
    $r = $conn->query("
        SELECT
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) < 36 THEN 1 ELSE 0 END)  AS b0_2,
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE())>=36 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE())<60  THEN 1 ELSE 0 END) AS b3_5,
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE())>=60 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE())<96  THEN 1 ELSE 0 END) AS b5_7,
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE())>=96 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE())<120 THEN 1 ELSE 0 END) AS b8_10,
          SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE())>=120 THEN 1 ELSE 0 END) AS b10p
        FROM employees
        WHERE ($ageCond)
          AND date_hired IS NOT NULL
          AND (date_resigned IS NULL OR date_resigned > CURDATE())
    ")->fetch_assoc();
    $retCounts = [
        (int)($r['b0_2'] ?? 0),
        (int)($r['b3_5'] ?? 0),
        (int)($r['b5_7'] ?? 0),
        (int)($r['b8_10'] ?? 0),
        (int)($r['b10p'] ?? 0),
    ];

    // Age distribution + avg (active only) filtered by same age
    $a = $conn->query("
        SELECT
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 20 AND 29 THEN 1 ELSE 0 END) AS a20_29,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 30 AND 39 THEN 1 ELSE 0 END) AS a30_39,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 40 AND 49 THEN 1 ELSE 0 END) AS a40_49,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 50 AND 59 THEN 1 ELSE 0 END) AS a50_59,
          SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 60 THEN 1 ELSE 0 END) AS a60p,
          AVG(TIMESTAMPDIFF(YEAR, birthdate, CURDATE())) AS avg_age
        FROM employees
        WHERE ($ageCond)
          AND birthdate IS NOT NULL
          AND date_hired <= CURDATE()
          AND (date_resigned IS NULL OR date_resigned > CURDATE())
    ")->fetch_assoc();
    $ageCounts = [
        (int)($a['a20_29'] ?? 0),
        (int)($a['a30_39'] ?? 0),
        (int)($a['a40_49'] ?? 0),
        (int)($a['a50_59'] ?? 0),
        (int)($a['a60p'] ?? 0),
    ];
    $avgAge = isset($a['avg_age']) ? round((float)$a['avg_age'], 1) : 0;

    // Terminated distribution filtered by age
    $t = $conn->query("
        SELECT
          SUM(CASE WHEN employment_status='Retired' THEN 1 ELSE 0 END) AS retired,
          SUM(CASE WHEN employment_status='Resigned' THEN 1 ELSE 0 END) AS resigned,
          SUM(CASE WHEN employment_status='Fired'   THEN 1 ELSE 0 END) AS fired
        FROM employees
        WHERE ($ageCond)
          AND employment_status IN ('Retired','Resigned','Fired')
    ")->fetch_assoc();
    $termCounts = [
        (int)($t['retired'] ?? 0),
        (int)($t['resigned'] ?? 0),
        (int)($t['fired'] ?? 0),
    ];

    /* Dept distribution within this age range (active now) */
    $deptLabelsAll = [];
    $dlRes = $conn->query("
        SELECT COALESCE(department, 'Unassigned') AS dept, COUNT(*) AS total
        FROM employees
        WHERE date_hired <= NOW()
          AND (date_resigned IS NULL OR date_resigned > NOW())
        GROUP BY dept
        ORDER BY total DESC
    ");
    if ($dlRes) {
        while ($row = $dlRes->fetch_assoc()) {
            $deptLabelsAll[] = $row['dept'];
        }
    }
    $countsMap = [];
    $dcRes = $conn->query("
        SELECT COALESCE(department, 'Unassigned') AS dept, COUNT(*) AS total
        FROM employees
        WHERE ($ageCond)
          AND date_hired <= NOW()
          AND (date_resigned IS NULL OR date_resigned > NOW())
        GROUP BY dept
    ");
    if ($dcRes) {
        while ($row = $dcRes->fetch_assoc()) {
            $countsMap[$row['dept']] = (int)$row['total'];
        }
    }
    $deptCountsAge = [];
    foreach ($deptLabelsAll as $dl) {
        $deptCountsAge[] = $countsMap[$dl] ?? 0;
    }

    echo json_encode([
        'ageSelected'    => $age,
        'tcard'          => ['total'=>$totalAge, 'new'=>$newJoinedAge, 'terminated'=>$terminatedAge],
        'growth'         => ['labels'=>$growthLabels, 'counts'=>$growthCounts],
        'retention'      => ['counts'=>$retCounts],
        'age'            => ['counts'=>$ageCounts, 'avg'=>$avgAge],
        'terminatedDist' => ['counts'=>$termCounts],
        'dept'           => ['labels'=>$deptLabelsAll, 'counts'=>$deptCountsAge],
    ]);
    exit();
}
/* -------------------- END AJAX FILTERS -------------------- */

/* --- Metrics (All Departments) --- */
$totalEmployees = $conn->query("SELECT COUNT(*) AS total FROM employees")->fetch_assoc()['total'] ?? 0;
$newJoined = $conn->query("
    SELECT COUNT(*) AS total
    FROM employees
    WHERE MONTH(date_hired) = MONTH(CURRENT_DATE())
      AND YEAR(date_hired)  = YEAR(CURRENT_DATE())
")->fetch_assoc()['total'] ?? 0;

/* OFFBOARDED (TOTAL HISTORICAL) â€” not month-only */
$terminated = $conn->query("
    SELECT COUNT(*) AS total
    FROM employees
    WHERE employment_status IN ('Retired','Resigned','Fired')
")->fetch_assoc()['total'] ?? 0;

/* --- Last 5 months active employees (as of month-end) --- */
$growthLabels = [];
$growthCounts = [];
for ($i = 4; $i >= 0; $i--) {
    $monthEnd = new DateTime("last day of -$i month");
    $monthEnd->setTime(23, 59, 59);
    $endStr = $conn->real_escape_string($monthEnd->format('Y-m-d H:i:s'));
    $label = $monthEnd->format('M Y');
    $growthLabels[] = $label;

    $sql = "
        SELECT COUNT(*) AS total
        FROM employees
        WHERE date_hired <= '$endStr'
          AND (date_resigned IS NULL OR date_resigned > '$endStr')
    ";
    $count = $conn->query($sql)->fetch_assoc()['total'] ?? 0;
    $growthCounts[] = (int)$count;
}

/* --- Dept distribution of active employees (now) --- */
$deptLabels = [];
$deptCounts = [];
$deptRes = $conn->query("
    SELECT COALESCE(department, 'Unassigned') AS dept, COUNT(*) AS total
    FROM employees
    WHERE date_hired <= NOW()
      AND (date_resigned IS NULL OR date_resigned > NOW())
    GROUP BY dept
    ORDER BY total DESC
");
if ($deptRes) {
    while ($row = $deptRes->fetch_assoc()) {
        $deptLabels[] = $row['dept'];
        $deptCounts[] = (int)$row['total'];
    }
}

/* --- Employee retention buckets (active only) --- */
$retBuckets = ['0-2 yrs','3-5 yrs','5-7 yrs','8-10 yrs','10+ yrs'];
$retCounts = [0,0,0,0,0];
$retQ = $conn->query("
    SELECT
      SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) < 36 THEN 1 ELSE 0 END) AS b0_2,
      SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) >= 36 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) < 60 THEN 1 ELSE 0 END) AS b3_5,
      SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) >= 60 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) < 96 THEN 1 ELSE 0 END) AS b5_7,
      SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) >= 96 AND TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) < 120 THEN 1 ELSE 0 END) AS b8_10,
      SUM(CASE WHEN TIMESTAMPDIFF(MONTH, date_hired, CURDATE()) >= 120 THEN 1 ELSE 0 END) AS b10p
    FROM employees
    WHERE date_hired IS NOT NULL
      AND (date_resigned IS NULL OR date_resigned > CURDATE())
");
if ($retQ) {
    $r = $retQ->fetch_assoc();
    $retCounts = [
        (int)($r['b0_2'] ?? 0),
        (int)($r['b3_5'] ?? 0),
        (int)($r['b5_7'] ?? 0),
        (int)($r['b8_10'] ?? 0),
        (int)($r['b10p'] ?? 0),
    ];
}

/* --- Age distribution (active only) + average age --- */
$ageLabels = ['20-29 yrs','30-39 yrs','40-49 yrs','50-59 yrs','60+ yrs'];
$ageCounts = [0,0,0,0,0];
$avgAge = 0;
$ageRes = $conn->query("
    SELECT
      SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 20 AND 29 THEN 1 ELSE 0 END) AS a20_29,
      SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 30 AND 39 THEN 1 ELSE 0 END) AS a30_39,
      SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 40 AND 49 THEN 1 ELSE 0 END) AS a40_49,
      SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 50 AND 59 THEN 1 ELSE 0 END) AS a50_59,
      SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 60 THEN 1 ELSE 0 END) AS a60p,
      AVG(TIMESTAMPDIFF(YEAR, birthdate, CURDATE())) AS avg_age
    FROM employees
    WHERE birthdate IS NOT NULL
      AND date_hired <= CURDATE()
      AND (date_resigned IS NULL OR date_resigned > CURDATE())
");
if ($ageRes) {
    $a = $ageRes->fetch_assoc();
    $ageCounts = [
        (int)($a['a20_29'] ?? 0),
        (int)($a['a30_39'] ?? 0),
        (int)($a['a40_49'] ?? 0),
        (int)($a['a50_59'] ?? 0),
        (int)($a['a60p'] ?? 0),
    ];
    $avgAge = isset($a['avg_age']) ? round((float)$a['avg_age'], 1) : 0;
}

/* --- Terminated distribution: retired / resigned / fired --- */
$termLabels = ['Retired','Resigned','Fired'];
$termCounts = [0,0,0];
$termRes = $conn->query("
    SELECT
      SUM(CASE WHEN employment_status='Retired' THEN 1 ELSE 0 END) AS retired,
      SUM(CASE WHEN employment_status='Resigned' THEN 1 ELSE 0 END) AS resigned,
      SUM(CASE WHEN employment_status='Fired'   THEN 1 ELSE 0 END) AS fired
    FROM employees
    WHERE employment_status IN ('Retired','Resigned','Fired')
");
if ($termRes) {
    $t = $termRes->fetch_assoc();
    $termCounts = [
        (int)($t['retired'] ?? 0),
        (int)($t['resigned'] ?? 0),
        (int)($t['fired'] ?? 0),
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Employee 201 Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:'Segoe UI',sans-serif;background:#1e1e2d;color:#e5e7eb;}
        .wrapper{display:flex;max-width:100%;overflow-x:hidden;}
        .main-content{flex:1; padding:30px; min-height:100vh; margin-left:220px; background:none; overflow-x:hidden;}
        .header{margin-bottom:30px;}
        .header h1{color:#f3f4f6;font-size:28px;margin:0 0 6px}
        .header p{color:#9ca3af;margin:0}

        .block-row{display:grid;grid-template-columns: 1fr 2fr 1fr;gap:20px;width:100%;margin-bottom:20px;}
        .block-row-3{display:grid;grid-template-columns: repeat(3, 1fr);gap:20px;width:100%;}
        @media (max-width: 1100px){.block-row, .block-row-3{ grid-template-columns: 1fr; }}

        .card-like{ width:100%; height:240px; background:#1f2937; border-radius:16px; box-shadow:0 4px 12px rgba(0,0,0,.3); padding:18px; position:relative; }
        .t-card{ overflow:hidden; background:transparent; box-shadow:none; }
        .t-line-h{position:absolute;left:10%;right:10%;top:105px;height:2px;background:rgba(229,231,235,.4);border-radius:4px;}
        .t-line-v{position:absolute;top:106px;left:50%;transform:translateX(-50%);width:2px;height:90px;background:rgba(229,231,235,.4);border-radius:4px;}
        .t-top,.t-left,.t-right{position:absolute;text-align:center;}
        .t-label{display:block;font-size:12px;color:#9ca3af;}
        .t-value{display:block;margin-top:3px;font-weight:700;font-size:clamp(24px,2.5vw,36px);color:#f3f4f6;}

        .clickable{ cursor:pointer; transition:opacity .15s ease; }
        .clickable:hover{ opacity:.85; text-decoration: underline; text-underline-offset: 3px; }

        .chart-card,.donut-card{ display:flex; flex-direction:column; }
        .chart-title{margin:0 0 8px;font-size:16px;color:#f3f4f6;}
        .chart-wrap,.donut-wrap{flex:1; position:relative;}
        canvas{max-width:100%; max-height:95%;}

        /* Chips */
        .dept-chip, .age-chip{
            position:absolute; top:12px; right:12px;
            background:rgba(255,255,255,0.1);
            border:1px solid rgba(255,255,255,0.18);
            padding:6px 10px; border-radius:9999px;
            font-size:12px; color:#0b1020; display:none; font-weight:600;
        }
        .age-chip{ right:12px; }

  /* modal and table styles */
        .modal-backdrop {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            display: none;
            justify-content: center; align-items: center;
            z-index: 999;
        }
        .modal-backdrop.show { display: flex; }
        .modal-box {
            background: #232330;
            color: #fff;
            padding: 20px;
            border-radius: 10px;
            min-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            z-index: 1000;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            border-bottom: 1px solid #444;
            color: #e5e7eb;
        }
        .modal-title{ margin:0; font-size:16px; color:#f3f4f6; }
        .modal-close{ background:transparent; border:none; color:#e5e7eb; font-size:18px; cursor:pointer; }
        .modal-body{ padding:0; background:#111827; overflow:auto; }
        .table{ width:100%; border-collapse:collapse; font-size:14px; }
        .table thead th{
            position:sticky; top:0; background:#0f172a; color:#9ca3af;
            text-align:left; padding:12px; font-weight:600; border-bottom:1px solid rgba(255,255,255,.06);
        }
        .table tbody td{ padding:12px; border-bottom:1px solid rgba(255,255,255,.04); color:#e5e7eb; }
        .empty-state{ padding:22px; color:#9ca3af; text-align:center; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>!</p>
        </div>

        <!-- Row 1 -->
        <div class="block-row">
            <!-- 1/4 T-card -->
            <div class="card-like t-card">
                <div class="t-line-h"></div>
                <div class="t-line-v"></div>
                <div class="t-top" id="t-total" style="top:32px;left:50%;transform:translateX(-50%);">
                    <span class="t-value" id="tTotalVal"><?= (int)$totalEmployees ?></span>
                    <span class="t-label">Total Employees</span>
                </div>
                <div class="t-left clickable" id="openNewModal" style="top:115px;left:6%;right:52%;">
                    <span class="t-value" id="tNewVal"><?= (int)$newJoined ?></span>
                    <span class="t-label">New Hires</span>
                </div>
                <div class="t-right clickable" id="openTerminatedModal" style="top:115px;left:52%;right:6%;">
                    <span class="t-value" id="tTermVal"><?= (int)$terminated ?></span>
                    <span class="t-label">Terminated</span>
                </div>
            </div>

            <!-- 2/4 Mixed Chart (Bar + Line) -->
            <div class="card-like chart-card">
                <h3 class="chart-title"><i class="fa-solid fa-chart-column"></i> Employee Growth Trend</h3>
                <div class="chart-wrap">
                    <canvas id="growthBar"></canvas>
                </div>
            </div>

            <!-- 1/4 Donut Chart (Departments) -->
            <div class="card-like donut-card" id="deptCard">
                <span class="dept-chip" id="deptChip"></span>
                <div class="donut-wrap" style="margin-top:10px;">
                    <canvas id="deptDonut"></canvas>
                </div>
            </div>
        </div>

        <!-- Row 2 -->
        <div class="block-row-3">
            <div class="card-like chart-card" style="height:270px;">
                <h3 class="chart-title"><i class="fa-solid fa-person-circle-check"></i> Employee Retention</h3>
                <div class="chart-wrap" style="margin-bottom:-10px">
                    <canvas id="retentionBar"></canvas>
                </div>
            </div>

            <div class="card-like donut-card" style="height:270px;" id="ageCard">
                <span class="age-chip" id="ageChip"></span>
                <h3 class="chart-title"><i class="fa-solid fa-user-group"></i> Age Distribution</h3>
                <div class="donut-wrap">
                    <canvas id="ageDonut"></canvas>
                </div>
            </div>

            <div class="card-like chart-card" style="height:270px;">
                <h3 class="chart-title"><i class="fa-solid fa-user-slash"></i> Terminated Distribution</h3>
                <div class="chart-wrap">
                    <canvas id="termBar"></canvas>
                </div>
            </div>
        </div>

    </div>
</div>
<!-- ===== Employee List Modal ===== -->
<div id="listBackdrop" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="listTitleEl">Employees</h2>
      <button id="listCloseBtn">&times;</button>
    </div>
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th id="dateColHeader">Date</th>
          <th>Department</th>
          <th>Position</th>
          <th id="statusColHead" style="display:none;">Date of Separation</th>
        </tr>
      </thead>
      <tbody id="listBodyEl"></tbody>
    </table>
    <div id="listEmptyEl" style="display:none; margin-top:10px; text-align:center;"></div>
  </div>
<!-- Employee List Modal -->
<div id="listBackdrop" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="listTitleEl">Employees</h2>
      <button id="listCloseBtn">&times;</button>
    </div>
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th id="dateColHeader">Date</th>
          <th>Department</th>
          <th>Position</th>
          <th id="dateOfSeparationHeader">Date of Separation</th>
        </tr>
      </thead>
      <tbody id="listBodyEl"></tbody>
    </table>
    <div id="listEmptyEl" style="display:none; margin-top:10px; text-align:center;"></div>
  </div>
</div>

<button id="openTerminatedModal">Open Terminated Employees Modal</button>



<script>
const growthLabels = <?= json_encode($growthLabels) ?>;
const growthCounts = <?= json_encode($growthCounts) ?>;
const deptLabels   = <?= json_encode($deptLabels) ?>;
const deptCounts   = <?= json_encode($deptCounts) ?>;
const deptCountNum = <?= count($deptLabels) ?>;

const retLabels = <?= json_encode($retBuckets) ?>;
const retCounts = <?= json_encode($retCounts) ?>;

const ageLabels = <?= json_encode($ageLabels) ?>;
const ageCounts = <?= json_encode($ageCounts) ?>;
const avgAge    = <?= json_encode($avgAge) ?>;

const termLabels = <?= json_encode($termLabels) ?>;
const termCounts = <?= json_encode($termCounts) ?>;

/* Helpers */
const LINE_OFFSET = 2; // move the line slightly higher than bars
function hexToRgba(hex, alpha){
  let h = hex.replace('#','');
  if (h.length === 3) h = h.split('').map(c=>c+c).join('');
  const bigint = parseInt(h, 16);
  const r = (bigint >> 16) & 255;
  const g = (bigint >> 8) & 255;
  const b = bigint & 255;
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/* Plugin: bar value labels atop bars (works for mixed by targeting dataset 0 if bar) */
const barValueLabels = {
  id: 'barValueLabels',
  afterDatasetsDraw(chart) {
    const {ctx} = chart;
    const meta0 = chart.getDatasetMeta(0);
    if (!meta0 || meta0.type !== 'bar') return;

    const dataset = chart.data.datasets[0];
    ctx.save();
    ctx.fillStyle = '#e5e7eb';
    ctx.font = '600 12px Segoe UI, sans-serif';
    meta0.data.forEach((bar, i) => {
      const v = dataset.data[i];
      if (v == null) return;
      if (chart.options.indexAxis === 'y') {
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(v, bar.x + 8, bar.y);
      } else {
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        ctx.fillText(v, bar.x, bar.y - 6);
      }
    });
    ctx.restore();
  }
};

/* Plugin: center text (number + subtext) for doughnuts */
const donutCenterText = {
  id: 'donutCenterText',
  afterDraw(chart, args, pluginOptions) {
    if (chart.config.type !== 'doughnut') return;
    const meta = chart.getDatasetMeta(0);
    if (!meta?.data?.length) return;
    const {ctx} = chart;
    const {x, y} = meta.data[0].getProps(['x','y'], true);

    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#e5e7eb';
    ctx.font = '700 24px Segoe UI, sans-serif';
    ctx.fillText(pluginOptions.text ?? '', x, y - 8);
    if (pluginOptions.subtext) {
      ctx.font = '500 12px Segoe UI, sans-serif';
      ctx.fillText(pluginOptions.subtext, x, y + 14);
    }
    ctx.restore();
  }
};

/* ===== Build charts (All Departments initial) ===== */
const DEFAULT_GROWTH_COLOR = '#3b82f6';
const DEFAULT_RETENTION_COLOR = '#10b981';

// Mixed chart: bar + line overlay (line transparent, no points, slightly higher)
const growthCtx = document.getElementById('growthBar');
const growthChart = new Chart(growthCtx, {
  data: {
    labels: growthLabels,
    datasets: [
      { // Bars
        type: 'bar',
        data: growthCounts,
        backgroundColor: DEFAULT_GROWTH_COLOR,
        borderRadius: 6,
        borderSkipped: false,
        order: 2
      },
      { // Transparent trend line (no points), shifted higher
        type: 'line',
        data: growthCounts.map(v => v + LINE_OFFSET),
        borderColor: hexToRgba(DEFAULT_GROWTH_COLOR, 0.5),
        pointRadius: 0,
        pointHoverRadius: 0,
        borderWidth: 2,
        fill: false,
        tension: 0.35,
        order: 3 // draw on top of bars
      }
    ]
  },
  options: {
    maintainAspectRatio: false,
    scales: {
      y:{beginAtZero:true, grid:{display:false}, ticks:{color:'#e5e7eb'}},
      x:{grid:{display:false}, ticks:{color:'#e5e7eb'}}
    },
    plugins: { legend:{display:false}, tooltip:{enabled:true} }
  },
  plugins:[barValueLabels]
});

// ensure y-axis max = max(data) + 5 (use bar dataset[0])
function applyGrowthMax(chart){
  const arr = chart.data.datasets[0].data || [];
  const max = Math.max(0, ...arr);
  chart.options.scales.y.max = max + 5;
  chart.update();
}
applyGrowthMax(growthChart);

const donutColors = ['#60a5fa','#34d399','#fbbf24','#f472b6','#a78bfa','#f87171','#22d3ee','#c084fc','#93c5fd','#4ade80','#facc15','#f871b0'];
let currentDeptColors = donutColors.slice(0, deptLabels.length);

const deptChart = new Chart(document.getElementById('deptDonut'), {
  type: 'doughnut',
  data: {
    labels: deptLabels,
    datasets: [{ data: deptCounts, backgroundColor: currentDeptColors, borderWidth: 0 }]
  },
  options: {
    maintainAspectRatio: false, cutout: '60%',
    plugins: {
      legend: { display:false },
      tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.parsed}` } },
      donutCenterText: { text: String(deptCountNum), subtext: 'Departments' }
    },
    onClick: (evt, elements) => handleDeptClick(evt, elements)
  },
  plugins:[donutCenterText]
});

const retentionChart = new Chart(document.getElementById('retentionBar'), {
  type: 'bar',
  data: { labels: <?= json_encode($retBuckets) ?>, datasets: [{ data: retCounts, backgroundColor: DEFAULT_RETENTION_COLOR, borderRadius: 6, borderSkipped: false }] },
  options: {
    maintainAspectRatio: false,
    layout: { padding: { top: 25 } },
    scales: { y:{beginAtZero:true,grid:{display:false},ticks:{color:'#e5e7eb'}}, x:{grid:{display:false},ticks:{color:'#e5e7eb'}} },
    plugins: { legend:{display:false}, tooltip:{callbacks:{label:(c)=>` ${c.label}: ${c.formattedValue}`}} }
  },
  plugins:[barValueLabels]
});

const ageSliceColors = ['#93c5fd','#60a5fa','#34d399','#fbbf24','#f87171']; // 20s..60+
const ageChart = new Chart(document.getElementById('ageDonut'), {
  type: 'doughnut',
  data: { labels: <?= json_encode($ageLabels) ?>, datasets: [{ data: ageCounts, backgroundColor: ageSliceColors, borderWidth: 0 }] },
  options: {
    maintainAspectRatio: false, cutout: '60%',
    plugins: {
      legend:{display:false},
      tooltip:{callbacks:{label:(c)=> ` ${c.label}: ${c.parsed}`}},
      donutCenterText:{ text:String(<?= json_encode($avgAge) ?>), subtext:'Avg. Age' }
    },
    onClick: (evt, elements) => handleAgeClick(evt, elements)
  },
  plugins:[donutCenterText]
});

const termChart = new Chart(document.getElementById('termBar'), {
  type: 'bar',
  data: { labels: termLabels, datasets: [{ data: termCounts, backgroundColor: ['#a78bfa','#fbbf24','#f87171'], borderRadius: 6, borderSkipped: false }] },
  options: {
    indexAxis:'y', maintainAspectRatio:false,
    scales:{ x:{beginAtZero:true,grid:{display:false},ticks:{color:'#e5e7eb'}}, y:{grid:{display:false},ticks:{color:'#e5e7eb'}} },
    plugins:{ legend:{display:false}, tooltip:{callbacks:{label:(c)=>` ${c.label}: ${c.formattedValue}`}} },
    layout:{ padding:{ right:12 } }
  },
  plugins:[barValueLabels]
});

/* ===== Interactivity: filter by department OR age (mutually exclusive) ===== */
const deptChipEl = document.getElementById('deptChip');
const ageChipEl  = document.getElementById('ageChip');
let selectedDept = null;
let selectedAge  = null;

function setDeptChip(name, colorHex) {
  if (name) {
    deptChipEl.textContent = name;
    deptChipEl.style.display = 'inline-block';
    deptChipEl.style.background = colorHex;
    deptChipEl.style.color = '#0b1020';
    deptChipEl.style.borderColor = 'rgba(0,0,0,0.15)';
  } else {
    deptChipEl.style.display = 'none';
  }
}
function setAgeChip(name, colorHex) {
  if (name) {
    ageChipEl.textContent = name;
    ageChipEl.style.display = 'inline-block';
    ageChipEl.style.background = colorHex;
    ageChipEl.style.color = '#0b1020';
    ageChipEl.style.borderColor = 'rgba(0,0,0,0.15)';
  } else {
    ageChipEl.style.display = 'none';
  }
}

function shadeDeptColors(activeIdx) {
  const base = currentDeptColors;
  deptChart.data.datasets[0].backgroundColor = base.map((c, i) => {
    if (activeIdx === null) return c;
    return i === activeIdx ? c : 'rgba(229,231,235,0.25)';
  });
}
function shadeAgeColors(activeIdx) {
  const base = ageSliceColors;
  ageChart.data.datasets[0].backgroundColor = base.map((c, i) => {
    if (activeIdx === null) return c;
    return i === activeIdx ? c : 'rgba(229,231,235,0.25)';
  });
}

// colorize growth (both bar and line) & retention bars
function colorizeBars(colorHex) {
  // Growth (bar dataset)
  growthChart.data.datasets[0].backgroundColor = colorHex;
  // Growth (line dataset) transparent color
  growthChart.data.datasets[1].borderColor = hexToRgba(colorHex, 0.5);
  growthChart.update();

  // Retention
  retentionChart.data.datasets[0].backgroundColor = colorHex;
  retentionChart.update();
}

async function handleDeptClick(evt, elements) {
  // Clear age selection if any
  if (selectedAge !== null) {
    selectedAge = null;
    setAgeChip(null);
    shadeAgeColors(null);
    // restore dept chart to originals
    deptChart.data.labels = [...originals.dept.labels];
    deptChart.data.datasets[0].data = [...originals.dept.data];
    currentDeptColors = [...originals.dept.colors];
    deptChart.data.datasets[0].backgroundColor = currentDeptColors;
    deptChart.update();
    ageChart.options.plugins.donutCenterText.text = String(originals.age.avg);
    ageChart.update();
  }

  if (!elements.length) {
    if (selectedDept !== null) {
      selectedDept = null;
      setDeptChip(null);
      shadeDeptColors(null);
      deptChart.update();
      colorizeBars(DEFAULT_GROWTH_COLOR);
      restoreAll();
      return;
    }
    return;
  }

  const idx = elements[0].index;
  const deptName = deptChart.data.labels[idx];
  const sliceColor = currentDeptColors[idx];

  // Toggle off
  if (selectedDept === deptName) {
    selectedDept = null;
    setDeptChip(null);
    shadeDeptColors(null);
    deptChart.update();
    colorizeBars(DEFAULT_GROWTH_COLOR);
    restoreAll();
    return;
  }

  // Select dept
  selectedDept = deptName;
  shadeDeptColors(idx);
  deptChart.update();
  setDeptChip(deptName, sliceColor);
  colorizeBars(sliceColor);

  try {
    const res = await fetch(`${location.pathname}?dept=${encodeURIComponent(deptName)}`, { headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();

    // T-card
    document.getElementById('tTotalVal').textContent = data.tcard.total ?? 0;
    document.getElementById('tNewVal').textContent   = data.tcard.new ?? 0;
    document.getElementById('tTermVal').textContent  = data.tcard.terminated ?? 0;

    // Growth
    growthChart.data.labels = data.growth.labels;
    growthChart.data.datasets[0].data = data.growth.counts;                       // bar
    growthChart.data.datasets[1].data = data.growth.counts.map(v => v + LINE_OFFSET); // line (higher)
    applyGrowthMax(growthChart);

    // Retention
    retentionChart.data.datasets[0].data = data.retention.counts;
    retentionChart.update();

    // Age
    ageChart.data.datasets[0].data = data.age.counts;
    ageChart.options.plugins.donutCenterText.text = String(data.age.avg ?? 0);
    ageChart.update();

    // Terminated distribution
    termChart.data.datasets[0].data = data.terminatedDist.counts;
    termChart.update();

  } catch (e) { console.error('Dept filter fetch failed', e); }
}

async function handleAgeClick(evt, elements) {
  // Clear dept selection if any
  if (selectedDept !== null) {
    selectedDept = null;
    setDeptChip(null);
    shadeDeptColors(null);
    deptChart.update();
  }

  if (!elements.length) {
    if (selectedAge !== null) {
      selectedAge = null;
      setAgeChip(null);
      shadeAgeColors(null);
      ageChart.options.plugins.donutCenterText.text = String(originals.age.avg);
      ageChart.data.datasets[0].backgroundColor = ageSliceColors;
      ageChart.update();
      // restore dept donut
      deptChart.data.labels = [...originals.dept.labels];
      deptChart.data.datasets[0].data = [...originals.dept.data];
      currentDeptColors = [...originals.dept.colors];
      deptChart.data.datasets[0].backgroundColor = currentDeptColors;
      deptChart.update();
      colorizeBars(DEFAULT_GROWTH_COLOR);
      restoreAll();
    }
    return;
  }

  const idx = elements[0].index;
  const ageName = ageChart.data.labels[idx];
  const sliceColor = ageSliceColors[idx];

  // Toggle off
  if (selectedAge === ageName) {
    selectedAge = null;
    setAgeChip(null);
    shadeAgeColors(null);
    ageChart.options.plugins.donutCenterText.text = String(originals.age.avg);
    ageChart.data.datasets[0].backgroundColor = ageSliceColors;
    ageChart.update();
    // restore dept donut
    deptChart.data.labels = [...originals.dept.labels];
    deptChart.data.datasets[0].data = [...originals.dept.data];
    currentDeptColors = [...originals.dept.colors];
    deptChart.data.datasets[0].backgroundColor = currentDeptColors;
    deptChart.update();
    colorizeBars(DEFAULT_GROWTH_COLOR);
    restoreAll();
    return;
  }

  // Select age range
  selectedAge = ageName;
  shadeAgeColors(idx);
  ageChart.update();
  colorizeBars(sliceColor);
  setAgeChip(ageName, sliceColor);

  try {
    const res = await fetch(`${location.pathname}?age=${encodeURIComponent(ageName)}`, { headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();

    // T-card
    document.getElementById('tTotalVal').textContent = data.tcard.total ?? 0;
    document.getElementById('tNewVal').textContent   = data.tcard.new ?? 0;
    document.getElementById('tTermVal').textContent  = data.tcard.terminated ?? 0;

    // Growth
    growthChart.data.labels = data.growth.labels;
    growthChart.data.datasets[0].data = data.growth.counts;                        // bar
    growthChart.data.datasets[1].data = data.growth.counts.map(v => v + LINE_OFFSET); // line (higher)
    applyGrowthMax(growthChart);

    // Retention
    retentionChart.data.datasets[0].data = data.retention.counts;
    retentionChart.update();

    // Age donut: filtered counts + center avg age for selected range
    ageChart.data.datasets[0].data = data.age.counts;
    ageChart.options.plugins.donutCenterText.text = String(data.age.avg ?? 0);
    ageChart.update();

    // Department donut: show distribution within selected age
    deptChart.data.labels = data.dept.labels;
    deptChart.data.datasets[0].data = data.dept.counts;
    currentDeptColors = donutColors.slice(0, data.dept.labels.length);
    deptChart.data.datasets[0].backgroundColor = currentDeptColors;
    shadeDeptColors(null);
    deptChart.update();

    // Terminated distribution
    termChart.data.datasets[0].data = data.terminatedDist.counts;
    termChart.update();

  } catch (e) { console.error('Age filter fetch failed', e); }
}

/* Keep originals to restore when clearing filter */
const originals = {
  tTotal: document.getElementById('tTotalVal').textContent,
  tNew:   document.getElementById('tNewVal').textContent,
  tTerm:  document.getElementById('tTermVal').textContent,
  growth: { labels:[...growthChart.data.labels], data:[...growthChart.data.datasets[0].data] },
  retention: { data:[...retentionChart.data.datasets[0].data] },
  age: { data:[...ageChart.data.datasets[0].data], avg: <?= json_encode($avgAge) ?> },
  term: { data:[...termChart.data.datasets[0].data] },
  dept: {
    labels: [...deptChart.data.labels],
    data:   [...deptChart.data.datasets[0].data],
    colors: [...deptChart.data.datasets[0].backgroundColor]
  }
};

function restoreAll() {
  document.getElementById('tTotalVal').textContent = originals.tTotal;
  document.getElementById('tNewVal').textContent   = originals.tNew;
  document.getElementById('tTermVal').textContent  = originals.tTerm;

  growthChart.data.labels = [...originals.growth.labels];
  growthChart.data.datasets[0].data = [...originals.growth.data];                   // bar
  growthChart.data.datasets[1].data = [...originals.growth.data].map(v => v + LINE_OFFSET); // line (higher)
  growthChart.data.datasets[0].backgroundColor = DEFAULT_GROWTH_COLOR;
  growthChart.data.datasets[1].borderColor = hexToRgba(DEFAULT_GROWTH_COLOR, 0.5);
  growthChart.update();
  applyGrowthMax(growthChart);

  retentionChart.data.datasets[0].data = [...originals.retention.data];
  retentionChart.data.datasets[0].backgroundColor = DEFAULT_RETENTION_COLOR;
  retentionChart.update();

  ageChart.data.datasets[0].data = [...originals.age.data];
  ageChart.data.datasets[0].backgroundColor = ageSliceColors;
  ageChart.options.plugins.donutCenterText.text = String(originals.age.avg);
  ageChart.update();

  termChart.data.datasets[0].data = [...originals.term.data];
  termChart.update();

  // Dept donut restore
  deptChart.data.labels = [...originals.dept.labels];
  deptChart.data.datasets[0].data = [...originals.dept.data];
  currentDeptColors = [...originals.dept.colors];
  deptChart.data.datasets[0].backgroundColor = currentDeptColors;
  deptChart.update();
}

/* ===== Modal Logic for New / Terminated with smooth transitions ===== */
const listBackdrop = document.getElementById('listBackdrop');
const listTitleEl = document.getElementById('listTitleEl');
const dateColHeader = document.getElementById('dateColHeader');
const dateOfSeparationHeader = document.getElementById('dateOfSeparationHeader');
const listBodyEl = document.getElementById('listBodyEl');
const listEmptyEl = document.getElementById('listEmptyEl');
const listCloseBtn = document.getElementById('listCloseBtn');

function openTerminatedModal() {
    listTitleEl.textContent = "Terminated Employees This Month";
    dateColHeader.textContent = "Date Terminated";
    dateOfSeparationHeader.style.display = "table-cell";

    listBodyEl.innerHTML = '';
    listEmptyEl.style.display = 'none';
    listBackdrop.classList.add('show');

    const params = new URLSearchParams({ list: 'terminated' });
    fetch(location.pathname + "?" + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        const rows = data.rows;
        if (!rows.length) {
            listEmptyEl.style.display = 'block';
            listEmptyEl.textContent = "No records found.";
            return;
        }
        const frag = document.createDocumentFragment();
        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(row.name)}</td>
                <td>${escapeHtml(row.date)}</td>
                <td>${escapeHtml(row.department)}</td>
                <td>${escapeHtml(row.position)}</td>
                <td>${escapeHtml(row.dateofseparation)}</td>
            `;
            frag.appendChild(tr);
        });
        listBodyEl.appendChild(frag);
    })
    .catch(() => {
        listEmptyEl.style.display = 'block';
        listEmptyEl.textContent = "Failed to load records.";
    });
}

function closeListModal() {
    listBackdrop.classList.remove('show');
}

document.getElementById('openTerminatedModal').addEventListener('click', openTerminatedModal);
listCloseBtn.addEventListener('click', closeListModal);
listBackdrop.addEventListener('click', (e) => {
    if (e.target === listBackdrop) {
        closeListModal();
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeListModal();
    }
});

// Helper to escape HTML
function escapeHtml(str) {
    return String(str).replace(/&/g, "&amp;")
                     .replace(/</g, "&lt;")
                     .replace(/>/g, "&gt;")
                     .replace(/"/g, "&quot;")
                     .replace(/'/g, "&#39;");
}[s];
  
</script>
<?php include '../includes/footer.php'; ?>

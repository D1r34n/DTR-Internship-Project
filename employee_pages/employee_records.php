<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

date_default_timezone_set('UTC');

require_once '../db.php';

$employeeId = $_SESSION['user_id'];

$startDate = $_GET['start'] ?? null;
$endDate   = $_GET['end'] ?? null;

if (!$startDate && !$endDate) {
    // default = current month
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-t');
}

$stmt = $pdo->prepare("
    SELECT 
        date,
        time_in,
        time_out,
        total_work_hours,
        status
    FROM attendance
    WHERE employee_id = ?
    AND date BETWEEN ? AND ?
    ORDER BY date ASC
");
$stmt->execute([$employeeId, $startDate, $endDate]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>
    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="employee_records.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../sidebar.php'; ?>

    <?php
    $current_page = 'records';
    include '../topbar.php'; ?>

    <div class="recordBoxWrapper">
        <div class="recordBox">
            <div class="recordHeader">

                <div class="recordTitle">
                    <?= date('F d, Y', strtotime($startDate)) ?>
                    -
                    <?= date('F d, Y', strtotime($endDate)) ?>
                </div>

                <form method="GET" class="datePickerForm">
                    <input 
                        type="date" 
                        name="start" 
                        value="<?= $startDate ?>"
                    >

                    <span style="color:#aaa;">to</span>

                    <input 
                        type="date" 
                        name="end" 
                        value="<?= $endDate ?>"
                    >

                    <button type="submit">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </form>

            </div>

            <div class="gantt">

                <?php foreach ($records as $row): ?>
                    <?php
                        if (!$row['time_in'] || !$row['time_out']) continue;

                        // 🔹 Combine date + time
                        $start = strtotime($row['date'] . ' ' . $row['time_in']);
                        $end   = strtotime($row['date'] . ' ' . $row['time_out']);

                        // 🔹 Handle cross-midnight
                        if ($end <= $start) {
                            $end = strtotime('+1 day', $end);
                        }

                        // 🔹 Dynamic range (2h padding)
                        $rangeStart = strtotime('-2 hours', $start);
                        $rangeEnd   = strtotime('+2 hours', $end);
                        $range = $rangeEnd - $rangeStart;

                        // 🔹 Bar position
                        $left = (($start - $rangeStart) / $range) * 100;
                        $width = (($end - $start) / $range) * 100;
                    ?>

                    <div class="gantt-row">
                        <div class="gantt-label">
                            <?= date('M d', strtotime($row['date'])) ?>
                        </div>

                        <div class="gantt-bar-container"
                        data-range-start="<?= $rangeStart ?>"
                        data-range-end="<?= $rangeEnd ?>">
                            <!-- Gantt cursor -->
                            <div class="gantt-cursor">
                                <div class="gantt-cursor-line"></div>
                                <div class="gantt-cursor-label"></div>
                            </div>

                            <!-- 🔹 Dynamic scale (aligned perfectly) -->
                            <div class="gantt-scale">
                                <?php
                                    $step = 3600; // 1 hour

                                    for ($t = $rangeStart; $t <= $rangeEnd; $t += $step):
                                        $pos = (($t - $rangeStart) / ($rangeEnd - $rangeStart)) * 100;
                                ?>
                                    <div class="gantt-scale-item" style="left: <?= $pos ?>%">
                                        <?= date('g:i A', $t) ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <?php
                                $startLabel = date('g:i A', $start);
                                $endLabel = date('g:i A', $end);

                                $startPos = (($start - $rangeStart) / $range) * 100;
                                $endPos   = (($end - $rangeStart) / $range) * 100;
                            ?>

                            <div class="gantt-marker start"
                                style="left: <?= $startPos ?>%"
                                title="Start: <?= $startLabel ?>">
                            </div>

                            <!-- Shift end marker -->
                            <div class="gantt-marker end"
                                style="left: <?= $endPos ?>%"
                                title="End: <?= $endLabel ?>">
                            </div>

                            <!-- 🔹 Bar -->
                            <div 
                                class="gantt-bar"
                                style="left: <?= $left ?>%; width: <?= $width ?>%;"
                                title="In: <?= date('M d g:i A', $start) ?> | Out: <?= date('M d g:i A', $end) ?>">
                            </div>

                        </div>
                    </div>

                <?php endforeach; ?>

            </div>
        </div>
    </div>

<script>
document.querySelectorAll('.gantt-bar-container').forEach(container => {
    
    const line = container.querySelector('.gantt-cursor-line');
    const label = container.querySelector('.gantt-cursor-label');
    
    // We target the SCALE specifically because that's what the PHP loop uses
    const scale = container.querySelector('.gantt-scale');

    const rangeStart = parseInt(container.dataset.rangeStart);
    const rangeEnd = parseInt(container.dataset.rangeEnd);
    const range = rangeEnd - rangeStart;

    container.addEventListener('mousemove', (e) => {
        const rect = container.getBoundingClientRect();
        let x = e.clientX - rect.left;
        let percent = Math.max(0, Math.min(1, x / rect.width));

        const time = rangeStart + (percent * range);
        const date = new Date(time * 1000);

        const timeString = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
        });


        const left = percent * 100;
        line.style.left = left + '%';
        label.style.left = left + '%';
        label.textContent = timeString;
    });
});
</script>
</body>
</html>
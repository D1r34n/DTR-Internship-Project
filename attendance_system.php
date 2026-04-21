<!-- PHP -->
<?php
require_once 'db.php';

date_default_timezone_set('Asia/Manila');

// Detect if running from CLI (cron job / task scheduler)
$isCLI = (php_sapi_name() === 'cli');

$testDate  = $isCLI ? null : ($_GET['test_date']  ?? null);
$startDate = $isCLI ? null : ($_GET['start_date'] ?? null);
$endDate   = $isCLI ? null : ($_GET['end_date']   ?? null);
$debug     = $isCLI ? true  : isset($_GET['debug']);
$skipGuard = false;
$logs      = [];
$ran       = false;

// If using CLI auto-run without any GET params needed
if ($isCLI) {
    $_GET['run'] = '1';
}

// System get state
function getSystemValue($pdo, $key) {
    $stmt = $pdo->prepare("SELECT value FROM system_state WHERE key_name = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

// System set state
function setSystemValue($pdo, $key, $value) {
    $stmt = $pdo->prepare("
        INSERT INTO system_state (key_name, value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$key, $value]);
}

// Build list of dates to process
$datesToProcess = [];

if ($startDate && $endDate) {
    $skipGuard = true;
    $current = strtotime($startDate);
    $end     = strtotime($endDate);
    while ($current <= $end) {
        $datesToProcess[] = date('Y-m-d', $current);
        $current = strtotime('+1 day', $current);
    }
} elseif ($testDate) {
    $skipGuard = true;
    $datesToProcess[] = $testDate;
} else {
    $datesToProcess[] = date('Y-m-d', strtotime('-1 day'));
}

// Lunch break variable
$BREAK_SECONDS = 3600;

if (isset($_GET['run'])) {
    $ran = true;
    $logs[] = ['type' => 'system', 'text' => 'Attendance Finalization System v2.0'];
    $logs[] = ['type' => 'system', 'text' => 'Timezone: Asia/Manila'];
    $logs[] = ['type' => 'system', 'text' => 'Processing ' . count($datesToProcess) . ' date(s)...'];
    $logs[] = ['type' => 'divider'];

    foreach ($datesToProcess as $processDate) {
        $lastRun = getSystemValue($pdo, 'attendance_last_finalize');

        if (!$skipGuard && $lastRun === $processDate) {
            $logs[] = ['type' => 'warn', 'text' => "[$processDate] Already finalized. Pick a date range to override."];
            continue;
        }

        $logs[] = ['type' => 'info', 'text' => "[$processDate] Starting finalization..."];

        $employees = $pdo->query("SELECT id FROM employees")->fetchAll(PDO::FETCH_COLUMN);
        $logs[] = ['type' => 'info', 'text' => "[$processDate] Found " . count($employees) . " employee(s)."];

        $countPresent = 0;
        $countAbsent  = 0;
        $countSkipped = 0;

        foreach ($employees as $employeeId) {
            $stmt = $pdo->prepare("
                SELECT time_in, time_out, is_rest_day
                FROM schedules
                WHERE employee_id = ? AND work_date = ?
            ");
            $stmt->execute([$employeeId, $processDate]);
            $sched = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sched || $sched['is_rest_day'] || !$sched['time_in'] || $sched['time_in'] === '00:00:00') {
                $logs[] = ['type' => 'mute', 'text' => "  [EMP#$employeeId] Skipped — rest day or no schedule."];
                $countSkipped++;
                continue;
            }

            $stmt = $pdo->prepare("
                INSERT INTO attendance (employee_id, date, scheduled_time_in, scheduled_time_out, status)
                VALUES (?, ?, ?, ?, 'absent')
                ON DUPLICATE KEY UPDATE
                    scheduled_time_in  = VALUES(scheduled_time_in),
                    scheduled_time_out = VALUES(scheduled_time_out)
            ");
            $stmt->execute([$employeeId, $processDate, $sched['time_in'], $sched['time_out']]);

            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
            $stmt->execute([$employeeId, $processDate]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            $scheduledIn  = strtotime($processDate . ' ' . $sched['time_in']);
            $scheduledOut = strtotime($processDate . ' ' . $sched['time_out']);

            $late = $undertime = $overtime = $totalHours = 0;
            $status = 'absent';
            $overtime_status = 'none';

            if ($attendance['actual_time_in'] && $attendance['actual_time_out']) {
                $actualIn  = strtotime($attendance['actual_time_in']);
                $actualOut = strtotime($attendance['actual_time_out']);

                if ($actualIn > $scheduledIn)
                    $late = (int) floor(($actualIn - $scheduledIn) / 60);

                if ($actualOut < $scheduledOut)
                    $undertime = (int) floor(($scheduledOut - $actualOut) / 60);

                if ($actualOut > $scheduledOut) {
                    $overtime = (int) floor(($actualOut - $scheduledOut) / 60);
                    $overtime_status = 'pending';
                }

                $workedSeconds = $actualOut - $actualIn - $BREAK_SECONDS;
                $totalHours    = round(max(0, $workedSeconds / 3600), 2);
                $status        = $late > 0 ? 'late' : 'present';
                $countPresent++;

                $tag  = $status === 'late' ? 'warn' : 'success';
                $line = "  [EMP#$employeeId] {$status} | Late: {$late}m | OT: {$overtime}m | UT: {$undertime}m | Hours: {$totalHours}h";
                $logs[] = ['type' => $tag, 'text' => $line];

            } elseif ($attendance['actual_time_in'] && !$attendance['actual_time_out']) {
                $status = 'incomplete';
                $logs[] = ['type' => 'warn', 'text' => "  [EMP#$employeeId] incomplete — timed in but no time out."];
                $countPresent++;
            } else {
                $status = 'absent';
                $logs[] = ['type' => 'error', 'text' => "  [EMP#$employeeId] absent."];
                $countAbsent++;
            }

            $stmt = $pdo->prepare("
                UPDATE attendance SET
                    scheduled_time_in  = ?,
                    scheduled_time_out = ?,
                    late_minutes       = ?,
                    undertime_minutes  = ?,
                    overtime_minutes   = ?,
                    total_work_hours   = ?,
                    status             = ?,
                    overtime_status    = ?
                WHERE employee_id = ? AND date = ?
            ");
            $stmt->execute([
                $sched['time_in'], $sched['time_out'],
                $late, $undertime, $overtime, $totalHours,
                $status, $overtime_status,
                $employeeId, $processDate
            ]);
        }

        setSystemValue($pdo, 'attendance_last_finalize', $processDate);

        $logs[] = ['type' => 'divider'];
        $logs[] = ['type' => 'success', 'text' => "[$processDate] Done. Present/Incomplete: $countPresent | Absent: $countAbsent | Skipped: $countSkipped"];
    }

    $logs[] = ['type' => 'divider'];
    $logs[] = ['type' => 'system', 'text' => 'All dates processed. System idle.'];
}

$lastRun = getSystemValue($pdo, 'attendance_last_finalize');

// ---- CLI OUTPUT ----
if ($isCLI) {
    foreach ($logs as $log) {
        if ($log['type'] === 'divider') {
            echo str_repeat('-', 52) . "
";
        } else {
            $prefix = match($log['type']) {
                'system'  => '## ',
                'success' => 'OK  ',
                'warn'    => '!!  ',
                'error'   => 'XX  ',
                'mute'    => '    ',
                default   => '->  ',
            };
            echo $prefix . $log['text'] . "
";
        }
    }
    exit(0);
}
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Finalization System</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Geist+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #0b0d0f;
    --surface:   #111418;
    --border:    #1e2328;
    --border2:   #2a3040;
    --text:      #cdd6e0;
    --mute:      #4a5568;
    --green:     #4ade80;
    --green-dim: #166534;
    --yellow:    #fbbf24;
    --red:       #f87171;
    --blue:      #60a5fa;
    --cyan:      #22d3ee;
    --purple:    #a78bfa;
    --prompt:    #38bdf8;
}

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem 1rem;
}

.window {
    width: 100%;
    max-width: 860px;
    background: var(--surface);
    border: 1px solid var(--border2);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 0 0 1px rgba(255,255,255,0.03), 0 24px 60px rgba(0,0,0,0.7);
}

/* Title bar */
.titlebar {
    background: #161b22;
    border-bottom: 1px solid var(--border);
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    user-select: none;
}

.dots { display: flex; gap: 6px; }
.dot {
    width: 12px; height: 12px;
    border-radius: 50%;
}
.dot-red    { background: #ff5f57; }
.dot-yellow { background: #ffbd2e; }
.dot-green  { background: #28c840; }

.titlebar-text {
    flex: 1;
    text-align: center;
    font-size: 11px;
    color: var(--mute);
    letter-spacing: 0.05em;
}

/* Controls panel */
.controls {
    background: #0d1117;
    border-bottom: 1px solid var(--border);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.controls-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.ctrl-label {
    color: var(--mute);
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    min-width: 80px;
}

.ctrl-label span { color: var(--cyan); }

input[type="date"], input[type="text"] {
    background: var(--bg);
    border: 1px solid var(--border2);
    color: var(--text);
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 4px;
    outline: none;
    transition: border-color 0.15s;
    width: 140px;
}

input[type="date"]:focus {
    border-color: var(--prompt);
}

.sep { color: var(--mute); font-size: 11px; }

.radio-group {
    display: flex;
    gap: 4px;
}

.radio-btn {
    background: var(--bg);
    border: 1px solid var(--border2);
    color: var(--mute);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    padding: 5px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
    letter-spacing: 0.03em;
}

.radio-btn:hover { border-color: var(--prompt); color: var(--text); }
.radio-btn.active { background: #0c2340; border-color: var(--prompt); color: var(--prompt); }

.btn-run {
    background: #0c2340;
    border: 1px solid var(--prompt);
    color: var(--prompt);
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    font-weight: 700;
    padding: 8px 20px;
    border-radius: 4px;
    cursor: pointer;
    letter-spacing: 0.05em;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-run:hover {
    background: var(--prompt);
    color: #000;
}

.btn-run .arrow { font-size: 14px; }

/* Terminal output */
.terminal {
    padding: 20px;
    min-height: 340px;
    max-height: 520px;
    overflow-y: auto;
    font-size: 12px;
    line-height: 1.75;
}

.terminal::-webkit-scrollbar { width: 4px; }
.terminal::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

.line { display: flex; gap: 8px; align-items: baseline; }

.line-system { color: var(--purple); }
.line-info   { color: var(--blue); }
.line-success { color: var(--green); }
.line-warn   { color: var(--yellow); }
.line-error  { color: var(--red); }
.line-mute   { color: var(--mute); }
.line-divider {
    border-top: 1px solid var(--border);
    margin: 6px 0;
}

.prefix {
    color: var(--mute);
    flex-shrink: 0;
    font-size: 11px;
}

/* Idle prompt */
.idle-prompt {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--mute);
    margin-top: 8px;
}

.idle-prompt .ps { color: var(--prompt); }
.idle-prompt .path { color: var(--green); }
.cursor-blink {
    display: inline-block;
    width: 8px; height: 14px;
    background: var(--prompt);
    animation: blink 1.1s step-end infinite;
    vertical-align: middle;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

/* Status bar */
.statusbar {
    background: #0d1117;
    border-top: 1px solid var(--border);
    padding: 8px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 10px;
    color: var(--mute);
    letter-spacing: 0.05em;
}

.status-item { display: flex; align-items: center; gap: 6px; }
.status-dot  { width: 6px; height: 6px; border-radius: 50%; background: var(--green); }
.status-dot.idle { background: var(--mute); }

/* Boot screen when nothing run yet */
.boot-screen {
    color: var(--mute);
    padding: 10px 0;
}
.boot-screen .ascii {
    color: #1e2328;
    font-size: 10px;
    line-height: 1.3;
    margin-bottom: 16px;
    white-space: pre;
}
.boot-line { margin-bottom: 2px; }
.boot-ok   { color: var(--green); }
.boot-ready { color: var(--prompt); margin-top: 12px; }
</style>
</head>
<body>

<div class="window">

    <!-- Title bar -->
    <div class="titlebar">
        <div class="dots">
            <div class="dot dot-red"></div>
            <div class="dot dot-yellow"></div>
            <div class="dot dot-green"></div>
        </div>
        <div class="titlebar-text">attendance_system.php — DTR Finalization</div>
    </div>

    <!-- Controls -->
    <form method="GET" action="">
        <input type="hidden" name="run" value="1">

        <div class="controls">

            <div class="controls-row">
                <span class="ctrl-label"><span>--</span>mode</span>
                <div class="radio-group" id="modeGroup">
                    <button type="button" class="radio-btn <?= (!$startDate && !$testDate) ? 'active' : '' ?>" onclick="setMode('yesterday')">yesterday</button>
                    <button type="button" class="radio-btn <?= $testDate ? 'active' : '' ?>" onclick="setMode('single')">single date</button>
                    <button type="button" class="radio-btn <?= $startDate ? 'active' : '' ?>" onclick="setMode('range')">date range</button>
                </div>
            </div>

            <!-- Single date -->
            <div class="controls-row" id="row-single" style="display:<?= $testDate ? 'flex' : 'none' ?>">
                <span class="ctrl-label"><span>--</span>date</span>
                <input type="date" name="test_date" id="inp-single"
                    value="<?= htmlspecialchars($testDate ?? date('Y-m-d')) ?>"
                    max="<?= date('Y-m-d') ?>">
            </div>

            <!-- Date range -->
            <div class="controls-row" id="row-range" style="display:<?= $startDate ? 'flex' : 'none' ?>">
                <span class="ctrl-label"><span>--</span>from</span>
                <input type="date" name="start_date" id="inp-start"
                    value="<?= htmlspecialchars($startDate ?? date('Y-m-01')) ?>"
                    max="<?= date('Y-m-d') ?>">
                <span class="sep">→</span>
                <input type="date" name="end_date" id="inp-end"
                    value="<?= htmlspecialchars($endDate ?? date('Y-m-d')) ?>"
                    max="<?= date('Y-m-d') ?>">
            </div>

            <div class="controls-row">
                <button type="submit" class="btn-run">
                    <span class="arrow">▶</span> Execute-Finalize
                </button>
            </div>

        </div>
    </form>

    <!-- Terminal output -->
    <div class="terminal" id="terminal">

        <?php if (!$ran): ?>
        <div class="boot-screen">
            <div class="ascii">╔══════════════════════════════════════════════════╗
║     DTR ATTENDANCE FINALIZATION SYSTEM v2.0      ║
╚══════════════════════════════════════════════════╝</div>
            <div class="boot-line"><span style="color:var(--mute)">[  OK  ]</span> <span class="boot-ok">Loaded database connection</span></div>
            <div class="boot-line"><span style="color:var(--mute)">[  OK  ]</span> <span class="boot-ok">Timezone set: Asia/Manila</span></div>
            <div class="boot-line"><span style="color:var(--mute)">[  OK  ]</span> <span class="boot-ok">Break deduction: 3600s (1 hour)</span></div>
            <?php if ($lastRun): ?>
            <div class="boot-line"><span style="color:var(--mute)">[  OK  ]</span> <span style="color:var(--yellow)">Last finalized: <?= htmlspecialchars($lastRun) ?></span></div>
            <?php endif; ?>
            <div class="boot-ready">System ready. Select mode and click Execute-Finalize.</div>
        </div>
        <?php endif; ?>

        <?php foreach ($logs as $log): ?>
            <?php if ($log['type'] === 'divider'): ?>
                <div class="line-divider"></div>
            <?php else: ?>
                <div class="line line-<?= $log['type'] ?>">
                    <?php if ($log['type'] === 'system'): ?>
                        <span class="prefix">##</span>
                    <?php elseif ($log['type'] === 'success'): ?>
                        <span class="prefix">✓ </span>
                    <?php elseif ($log['type'] === 'warn'): ?>
                        <span class="prefix">⚠ </span>
                    <?php elseif ($log['type'] === 'error'): ?>
                        <span class="prefix">✗ </span>
                    <?php elseif ($log['type'] === 'mute'): ?>
                        <span class="prefix">  </span>
                    <?php else: ?>
                        <span class="prefix">→ </span>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($log['text']) ?></span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($ran): ?>
        <div class="idle-prompt">
            <span class="ps">PS</span>
            <span class="path">DTR\Attendance</span>
            <span>&gt;</span>
            <span class="cursor-blink"></span>
        </div>
        <?php endif; ?>

    </div>

    <!-- Status bar -->
    <div class="statusbar">
        <div class="status-item">
            <div class="status-dot <?= $ran ? '' : 'idle' ?>"></div>
            <span><?= $ran ? 'COMPLETED' : 'IDLE' ?></span>
        </div>
        <div class="status-item">
            Last run: <?= $lastRun ? htmlspecialchars($lastRun) : 'never' ?>
        </div>
        <div class="status-item">
            <?= date('Y-m-d H:i:s') ?>
        </div>
    </div>

</div>

<script>
function setMode(mode) {
    document.querySelectorAll('.radio-btn').forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');

    document.getElementById('row-single').style.display = 'none';
    document.getElementById('row-range').style.display  = 'none';

    // Clear hidden inputs so they don't get submitted
    document.getElementById('inp-single').name = '';
    document.getElementById('inp-start').name  = '';
    document.getElementById('inp-end').name    = '';

    if (mode === 'single') {
        document.getElementById('row-single').style.display = 'flex';
        document.getElementById('inp-single').name = 'test_date';
    } else if (mode === 'range') {
        document.getElementById('row-range').style.display = 'flex';
        document.getElementById('inp-start').name = 'start_date';
        document.getElementById('inp-end').name   = 'end_date';
    }
}

// Auto-scroll terminal to bottom
const terminal = document.getElementById('terminal');
terminal.scrollTop = terminal.scrollHeight;
</script>

</body>
</html>
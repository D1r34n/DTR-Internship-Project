<?php
// Requires $cutoffs to be already fetched from DB before including this file.
function co_label(array $c): string {
    return date('M j, Y', strtotime($c['start_date'])) . ' – ' . date('M j, Y', strtotime($c['end_date']));
}

$today         = date('Y-m-d');
$currentCutoff = null;
foreach ($cutoffs as $c) {
    if ($today >= $c['start_date'] && $today <= $c['end_date']) {
        $currentCutoff = $c;
        break;
    }
}

$defaultStart = $currentCutoff !== null ? $currentCutoff['start_date'] : ($cutoffs[0]['start_date'] ?? null);
$defaultEnd   = $currentCutoff !== null ? $currentCutoff['end_date']   : ($cutoffs[0]['end_date']   ?? null);
$defaultLabel = $currentCutoff !== null ? 'Current Cut-Off' : (!empty($cutoffs) ? 'Selected Cut-Off' : 'Select Period');
$defaultRange = $currentCutoff !== null ? 'Current Cut-Off: '.co_label($currentCutoff) : (!empty($cutoffs) ? 'Selected Cut-Off: '.co_label($cutoffs[0]) : '');

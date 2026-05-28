<?php
// Requires $cutoffs, $today, and co_label() from cutoff_helpers.php.
$current_index  = null;
$previous_index = null;

foreach ($cutoffs as $idx => $c) {
    if ($today >= $c['start_date'] && $today <= $c['end_date']) {
        $current_index = $idx;
        break;
    }
}

if ($current_index !== null) {
    $previous_index = isset($cutoffs[$current_index + 1]) ? $current_index + 1 : null;
} else {
    $current_index  = 0;
    $previous_index = isset($cutoffs[1]) ? 1 : null;
}

if (isset($cutoffs[$current_index])):
    $cc       = $cutoffs[$current_index];
    $cc_label = co_label($cc);
?>
<li>
    <a class="dropdown-item cutoff-item active" href="#"
       data-start="<?= $cc['start_date'] ?>"
       data-end="<?= $cc['end_date'] ?>"
       data-btn-label="Current Cut-Off"
       data-range-label="Current Cut-Off: <?= htmlspecialchars($cc_label) ?>">
        <div class="vstack gap-0">
            <span>Current Cut-Off</span>
            <small class="text-meta"><?= htmlspecialchars($cc_label) ?></small>
        </div>
    </a>
</li>
<?php endif; ?>

<?php if ($previous_index !== null && isset($cutoffs[$previous_index])):
    $pc       = $cutoffs[$previous_index];
    $pc_label = co_label($pc);
?>
<li>
    <a class="dropdown-item cutoff-item" href="#"
       data-start="<?= $pc['start_date'] ?>"
       data-end="<?= $pc['end_date'] ?>"
       data-btn-label="Previous Cut-Off"
       data-range-label="Previous Cut-Off: <?= htmlspecialchars($pc_label) ?>">
        <div class="vstack gap-0">
            <span>Previous Cut-Off</span>
            <small class="text-meta"><?= htmlspecialchars($pc_label) ?></small>
        </div>
    </a>
</li>
<?php endif; ?>

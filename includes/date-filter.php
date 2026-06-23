<?php
/** Expects $fromDateOnly and $toDateOnly to be set (Y-m-d strings). */
?>
<div class="date-filter-bar d-flex flex-wrap align-items-center gap-3">
    <form method="get" class="d-flex flex-wrap align-items-center gap-2">
        <label class="small text-muted mb-0">From</label>
        <input type="date" name="from" value="<?= htmlspecialchars($fromDateOnly) ?>" class="form-control form-control-sm" style="width:150px;">
        <label class="small text-muted mb-0">To</label>
        <input type="date" name="to" value="<?= htmlspecialchars($toDateOnly) ?>" class="form-control form-control-sm" style="width:150px;">
        <button type="submit" class="btn btn-sm btn-dark">Apply</button>
        <div class="vr d-none d-md-block"></div>
        <a href="?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">7d</a>
        <a href="?from=<?= date('Y-m-d', strtotime('-30 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">30d</a>
        <a href="?from=<?= date('Y-m-d', strtotime('-90 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">90d</a>
        <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">This month</a>
    </form>
</div>

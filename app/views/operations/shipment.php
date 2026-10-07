<?php
$title = 'Shipment Details';
$module = 'shipments';
require __DIR__ . '/../partials/header.php';
$isAdmin = hasRole('administrator');
?>

<div class="page-heading mb-4">
    <div>
        <span class="eyebrow">Freight Tracking</span>
        <h1>Delivery Overview</h1>
        <p>Live status timeline, dispatch assignments, and verification documents.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($isAdmin && in_array($shipment['current_status'], ['pending', 'assigned'], true)): ?>
            <a class="btn btn-regc" href="index.php?route=shipments&amp;action=edit&amp;id=<?= (int) $shipment['id']; ?>" data-modal-link>
                <i class="bi bi-pencil-square"></i> Edit / Assign
            </a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <a class="btn btn-warning text-dark fw-bold" href="index.php?route=shipments&amp;action=email-tracking&amp;id=<?= (int) $shipment['id']; ?>" onclick="return confirm('Send tracking email with code <?= e($shipment['tracking_number']); ?> to the customer?');">
                <i class="bi bi-envelope-at-fill"></i> Email Customer
            </a>
            <a class="btn btn-success text-white fw-bold" href="index.php?route=shipments&amp;action=sms-tracking&amp;id=<?= (int) $shipment['id']; ?>" onclick="return confirm('Send tracking SMS with code <?= e($shipment['tracking_number']); ?> to the customer?');" title="Send tracking number via text message (SMS)">
                <i class="bi bi-phone-fill"></i> SMS Customer
            </a>
            <a class="btn btn-outline-secondary" href="index.php?route=shipments&amp;action=email-preview&amp;id=<?= (int) $shipment['id']; ?>" target="_blank" title="Preview what the customer receives">
                <i class="bi bi-eye"></i> Preview Email
            </a>
            <a class="btn btn-outline-secondary" href="index.php?route=diesel-form&amp;shipment_id=<?= (int) $shipment['id']; ?>&amp;truck_id=<?= (int) ($shipment['truck_id'] ?? 0); ?>&amp;driver_id=<?= (int) ($shipment['driver_id'] ?? 0); ?>" data-modal-link>
                <i class="bi bi-fuel-pump"></i> Log Fuel
            </a>
        <?php endif; ?>

        <?php if ($shipment['current_status'] === 'arrived'): ?>
            <a class="btn btn-regc" href="index.php?route=proof-form&amp;shipment_id=<?= (int) $shipment['id']; ?>" data-modal-link>
                <i class="bi bi-file-earmark-check"></i> Upload Proof of Delivery
            </a>
        <?php endif; ?>

        <a class="btn btn-outline-secondary" href="index.php?route=shipments">
            <i class="bi bi-list-ul"></i> All Shipments
        </a>
        <a class="btn btn-outline-secondary" href="index.php?route=tracking&amp;number=<?= e(urlencode($shipment['tracking_number'] ?? '')); ?>">
            <i class="bi bi-geo-alt"></i> Public Tracking
        </a>
    </div>
</div>

<?php if ($shipment['current_status'] === 'pending'): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>Assign an available truck and driver using <strong>Edit / Assign</strong> to dispatch this shipment.</div>
    </div>
<?php elseif ($shipment['current_status'] !== 'delivered'):
    $next = Operations::STATUSES[array_search($shipment['current_status'], Operations::STATUSES, true) + 1] ?? null;
    if ($next): ?>
    <div class="workspace-card status-update mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="flex-grow-1">
            <strong class="fs-6 text-dark d-block">Advance Delivery Milestone</strong>
            <p class="text-muted mb-0">Record the next checkpoint: <strong><?= e(ucwords(str_replace('_', ' ', $shipment['current_status']))); ?> &rarr; <?= e(ucwords(str_replace('_', ' ', $next))); ?></strong>.</p>
        </div>
        <a class="btn btn-regc" href="index.php?route=shipments&amp;action=status-form&amp;id=<?= (int) $shipment['id']; ?>" data-modal-link>
            <i class="bi bi-arrow-right-circle"></i> Mark as <?= e(ucwords(str_replace('_', ' ', $next))); ?>
        </a>
    </div>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div>Delivery completed. Assigned truck and driver have been released back to available status.</div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/shipment-detail.php'; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>

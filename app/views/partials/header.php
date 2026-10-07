<?php
require_once __DIR__ . '/../../models/Notification.php';
$unreadNotifCount = isLoggedIn() ? Notification::unreadCount((int) authUser()['id'], authUser()['role']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? APP_NAME ?? 'Fleet Management and Shipment Tracking'); ?> | REGC Trucking Services</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="public/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="sidebar-backdrop"></div>
<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button type="button" class="btn-sidebar-toggle" data-sidebar-toggle aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <h4 class="mb-0 fw-bold" style="font-size: 19px; color: var(--navy);"><?= e($title ?? APP_NAME ?? 'Fleet Management and Shipment Tracking'); ?></h4>
                <small class="text-muted d-none d-sm-block">REGC Trucking Services &middot; Logistics Cloud</small>
            </div>
        </div>
        <div class="topbar-right">
            <a href="index.php?route=notifications" class="notif-bell-btn" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="notif-badge" style="<?= $unreadNotifCount > 0 ? '' : 'display: none;'; ?>">
                    <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount; ?>
                </span>
            </a>
            <a href="index.php?route=account" class="user-profile" title="View Account Profile">
                <div class="avatar"><?= e(strtoupper(substr(authUser()['name'] ?? 'U', 0, 1))); ?></div>
                <div class="d-none d-md-block text-start">
                    <strong class="d-block text-truncate" style="max-width: 140px;"><?= e(authUser()['name'] ?? 'User'); ?></strong>
                    <small><?= e(ucfirst(authUser()['role'] ?? 'staff')); ?></small>
                </div>
            </a>
        </div>
    </header>
    <section class="dashboard-area">
    <?php foreach (['notice' => 'success', 'operation_error' => 'danger'] as $flashKey => $flashStyle): ?>
        <?php if (isset($_SESSION[$flashKey])): ?>
            <div class="alert alert-<?= $flashStyle; ?> alert-dismissible fade show shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
                <i class="bi <?= $flashStyle === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> fs-5"></i>
                <div class="flex-grow-1"><?= e($_SESSION[$flashKey]); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION[$flashKey]); ?>
        <?php endif; ?>
    <?php endforeach; ?>

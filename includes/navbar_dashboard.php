<?php
// includes/navbar_dashboard.php
// Usage: <?php include '../../includes/navbar_dashboard.php'; ?>
// Set $dashboard_title before including, e.g. $dashboard_title = 'StrayLink Admin';
// Set $shelter (object) before including if shelter dashboard

$title = $dashboard_title ?? 'StrayLink';
?>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <img src="../../assets/img/logo.webp" alt="StrayLink"> <?= htmlspecialchars($title) ?>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white small">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
            <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>
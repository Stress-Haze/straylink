<?php
// includes/navbar_dashboard.php
// Usage: include '../../includes/navbar_dashboard.php';
// Set $dashboard_title before including, e.g. $dashboard_title = 'StrayLink Admin'.
// Set $shelter (object) before including if shelter dashboard

$title = $dashboard_title ?? 'StrayLink';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <img
                class="navbar-logo"
                src="../../assets/img/logo.png"
                alt="StrayLink"
                style="height:34px; max-width:56px; width:auto; object-fit:contain; flex:0 0 auto; display:block;"
            >
            <span><?= htmlspecialchars($title) ?></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white small">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
            <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>
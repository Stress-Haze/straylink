<?php
// includes/navbar.php
// Usage from pages/: <?php include '../includes/navbar.php'; ?>
// Usage from dashboard/: <?php include '../../includes/navbar.php'; ?>
// Set $active_page before including, e.g. $active_page = 'gallery';

$active = $active_page ?? '';

// Detect depth for correct relative paths
$depth  = $nav_depth ?? 1; // 1 = pages/, 2 = dashboard/role/
$root   = str_repeat('../', $depth);
?>
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="<?= $root ?>pages/home.php">
            <img src="<?= $root ?>assets/img/logo.webp" alt="StrayLink"> StrayLink
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto gap-2">
                <li class="nav-item">
                    <a class="nav-link <?= $active === 'gallery' ? 'active' : '' ?>" href="<?= $root ?>pages/gallery.php">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active === 'blog' ? 'active' : '' ?>" href="<?= $root ?>pages/blog.php">Blog</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active === 'rescue' ? 'active' : '' ?>" href="<?= $root ?>pages/rescue.php">Report a Stray</a>
                </li>
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= $root ?>dashboard/admin/index.php">Dashboard</a></li>
                    <?php elseif (isShelter()): ?>
                        <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= $root ?>dashboard/shelter/index.php">Dashboard</a></li>
                    <?php elseif (isVolunteer()): ?>
                        <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= $root ?>dashboard/volunteer/index.php">Dashboard</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= $root ?>auth/logout.php">Logout</a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= $root ?>auth/login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm" href="<?= $root ?>auth/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
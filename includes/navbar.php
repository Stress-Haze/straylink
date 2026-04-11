<?php
$active = $active_page ?? '';

$depth = $nav_depth ?? 1;
$root = str_repeat('../', $depth);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="<?= $root ?>pages/home.php">
            <img
                class="navbar-logo"
                src="<?= $root ?>assets/img/logo.png"
                alt="StrayLink"
                style="height:34px; max-width:56px; width:auto; object-fit:contain; flex:0 0 auto; display:block;"
            >
            StrayLink
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto gap-2">
                <li class="nav-item">
                    <a class="nav-link <?= $active === 'home' ? 'active' : '' ?>" href="<?= $root ?>pages/home.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active === 'gallery' ? 'active' : '' ?>" href="<?= $root ?>pages/gallery.php">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active === 'shelters' ? 'active' : '' ?>" href="<?= $root ?>pages/shelters.php">Shelters</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active === 'lost_pets' ? 'active' : '' ?>" href="<?= $root ?>pages/lost_pets.php">Lost Pets</a>
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
                        <li class="nav-item">
                            <a class="nav-link <?= $active === 'account' ? 'active' : '' ?>" href="<?= $root ?>pages/account.php">My App</a>
                        </li>
                        <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= $root ?>auth/logout.php">Logout</a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-light btn-sm" href="<?= $root ?>auth/login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm" href="<?= $root ?>auth/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

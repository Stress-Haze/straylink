<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) redirect('dashboard/admin/index.php');
    if (isShelter()) redirect('dashboard/shelter/index.php');
    if (isVolunteer()) redirect('dashboard/volunteer/index.php');
    redirect('pages/account.php');
}

redirect('pages/home.php');
?>

<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) redirect('../dashboard/admin/index.php');
    elseif (isShelter()) redirect('../dashboard/shelter/index.php');
    elseif (isVolunteer()) redirect('../dashboard/volunteer/index.php');
    else redirect('../pages/account.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $member = loginMember($conn, $email, $password);
        if ($member === 'pending') {
            $error = "Your account is pending admin approval. You will be notified once approved.";
        } elseif ($member) {
            if (isAdmin()) redirect('../dashboard/admin/index.php');
            elseif (isShelter()) redirect('../dashboard/shelter/index.php');
            elseif (isVolunteer()) redirect('../dashboard/volunteer/index.php');
            else redirect('../pages/account.php');
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width: 100%; max-width: 420px;">
        <div class="text-center mb-4">
            <h2 class="fw-bold">StrayLink</h2>
            <p class="text-muted">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Login</button>
        </form>

        <p class="text-center mt-3 mb-0">
            Don't have an account? <a href="register.php">Register</a>
        </p>
    </div>
</div>

</body>
</html>

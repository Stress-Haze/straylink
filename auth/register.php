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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $city = sanitize($_POST['city']);
    $role = sanitize($_POST['role']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!in_array($role, ['user', 'volunteer', 'shelter'])) {
        $error = "Invalid role selected.";
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM members WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = "An account with this email already exists.";
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $is_verified = $role === 'user' ? 1 : 0;
            $stmt = mysqli_prepare($conn, "INSERT INTO members (full_name, email, password_hash, phone, city, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssssssi", $full_name, $email, $password_hash, $phone, $city, $role, $is_verified);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Account created successfully! You can now log in.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center min-vh-100 py-5">
    <div class="card shadow p-4" style="width: 100%; max-width: 480px;">
        <div class="text-center mb-4">
            <h2 class="fw-bold">StrayLink</h2>
            <p class="text-muted">Create your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" placeholder="e.g. Pokhara">
            </div>
            <div class="mb-3">
                <label class="form-label">I am registering as <span class="text-danger">*</span></label>
                <select name="role" class="form-select" required>
                    <option value="">-- Select Role --</option>
                    <option value="user">General User / Adopter</option>
                    <option value="volunteer">Volunteer</option>
                    <option value="shelter">Shelter / Organisation</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required>
                <small class="text-muted">Minimum 8 characters</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Create Account</button>
        </form>

        <p class="text-center mt-3 mb-0">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>
</div>

</body>
</html>

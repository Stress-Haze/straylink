<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['member_id'])) {
    die('You must be logged in to use this tool');
}

$member_id = (int)$_SESSION['member_id'];

// Get current data
$result = mysqli_query($conn, "SELECT id, full_name, email, role FROM members WHERE id = $member_id");
$member = mysqli_fetch_assoc($result);

if (!$member) {
    die('Could not find your account');
}

$updated = false;
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $new_role = mysqli_real_escape_string($conn, $_POST['new_role']);
    
    // Update database
    mysqli_query($conn, "UPDATE members SET role = '$new_role' WHERE id = $member_id");
    
    // Update session
    $_SESSION['role'] = $new_role;
    
    $updated = true;
    $message = "Role updated successfully! You are now a $new_role. Please <a href='auth/logout.php'>log out</a> and log back in to ensure everything works correctly.";
    
    // Refresh member data
    $result = mysqli_query($conn, "SELECT id, full_name, email, role FROM members WHERE id = $member_id");
    $member = mysqli_fetch_assoc($result);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Volunteer Role</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Fix Volunteer Role</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($updated): ?>
                            <div class="alert alert-success">
                                <?= $message ?>
                            </div>
                            <a href="dashboard/volunteer/index.php" class="btn btn-success w-100">Go to Volunteer Dashboard</a>
                        <?php else: ?>
                            <h5>Current Account Info</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>ID</th>
                                    <td><?= $member['id'] ?></td>
                                </tr>
                                <tr>
                                    <th>Name</th>
                                    <td><?= htmlspecialchars($member['full_name']) ?></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td><?= htmlspecialchars($member['email']) ?></td>
                                </tr>
                                <tr>
                                    <th>Current Role</th>
                                    <td><span class="badge bg-warning"><?= htmlspecialchars($member['role']) ?></span></td>
                                </tr>
                            </table>

                            <div class="alert alert-info">
                                <strong>Problem:</strong> Your account has role='user' but you need role='volunteer' to access the volunteer dashboard.
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Change role to:</label>
                                    <select name="new_role" class="form-select" required>
                                        <option value="volunteer" selected>volunteer</option>
                                        <option value="user">user</option>
                                        <option value="shelter">shelter</option>
                                        <option value="admin">admin</option>
                                    </select>
                                </div>
                                <button type="submit" name="confirm" class="btn btn-primary w-100">Update Role</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="check_session.php" class="btn btn-link">Back to Diagnostic</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

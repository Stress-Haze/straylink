<?php
function loginMember($conn, $email, $password) {
    $email = sanitize($email);
    $query = "SELECT * FROM members WHERE email = ? AND is_active = 1 LIMIT 1";
    $stmt  = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $member = mysqli_fetch_assoc($result);

    if ($member && password_verify($password, $member['password_hash'])) {
        // Block unverified volunteers and shelters
        if (in_array($member['role'], ['volunteer', 'shelter']) && !$member['is_verified']) {
            return 'pending';
        }
        $_SESSION['member_id'] = $member['id'];
        $_SESSION['full_name'] = $member['full_name'];
        $_SESSION['role']      = $member['role'];
        $_SESSION['email']     = $member['email'];
        return $member;
    }
    return false;
}

function logoutMember() {
    session_unset();
    session_destroy();
    redirect('../index.php');
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        redirect('../index.php');
    }
}
?>
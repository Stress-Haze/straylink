<?php
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['member_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function isAdmin() {
    return hasRole('admin');
}

function isShelter() {
    return hasRole('shelter');
}

function isVolunteer() {
    return hasRole('volunteer');
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "just now";
    if ($diff < 3600) return floor($diff / 60) . " mins ago";
    if ($diff < 86400) return floor($diff / 3600) . " hours ago";
    return floor($diff / 86400) . " days ago";
}

function addKarma($conn, $member_id, $points) {
    $member_id = (int)$member_id;
    $points = (int)$points;
    mysqli_query($conn, "UPDATE members SET karma = karma + $points WHERE id = $member_id");
}

function getKarmaLevel($karma) {
    if ($karma >= 50) return ['label' => 'Champion', 'color' => 'danger', 'icon' => '&#127942;'];
    if ($karma >= 25) return ['label' => 'Trusted', 'color' => 'warning', 'icon' => '&#11088;'];
    if ($karma >= 10) return ['label' => 'Contributor', 'color' => 'success', 'icon' => '&#127807;'];
    return ['label' => 'Newcomer', 'color' => 'secondary', 'icon' => '&#127793;'];
}

function getNextMilestone($karma) {
    if ($karma < 10) return ['target' => 10, 'label' => 'Contributor - unlock blog posting'];
    if ($karma < 25) return ['target' => 25, 'label' => 'Trusted - get a trusted badge'];
    if ($karma < 50) return ['target' => 50, 'label' => 'Champion - top community member'];
    return null;
}

function canPostBlog($karma) {
    return $karma >= 10;
}

function addNotification($conn, $member_id, $type, $message, $link = null) {
    $member_id = (int)$member_id;
    $type    = mysqli_real_escape_string($conn, $type);
    $title   = mysqli_real_escape_string($conn, $type);
    $body    = mysqli_real_escape_string($conn, $message);
    $link_sql = $link ? "'" . mysqli_real_escape_string($conn, $link) . "'" : 'NULL';
    mysqli_query($conn, "INSERT INTO notifications (member_id, type, title, body, link, is_read, created_at)
        VALUES ($member_id, '$type', '$title', '$body', $link_sql, 0, NOW())");
}

function notifyRoles($conn, array $roles, $type, $message, $link = null) {
    $placeholders = implode(',', array_map(fn($r) => "'" . mysqli_real_escape_string($conn, $r) . "'", $roles));
    $members = mysqli_query($conn, "SELECT id FROM members WHERE role IN ($placeholders) AND is_active = 1");
    while ($m = mysqli_fetch_assoc($members)) {
        addNotification($conn, $m['id'], $type, $message, $link);
    }
}

function getUnreadNotificationCount($conn, $member_id) {
    $member_id = (int)$member_id;
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM notifications WHERE member_id = $member_id AND is_read = 0"));
    return (int)$r['c'];
}
?>

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
    if ($diff < 3600) return floor($diff/60) . " mins ago";
    if ($diff < 86400) return floor($diff/3600) . " hours ago";
    return floor($diff/86400) . " days ago";
}

function addKarma($conn, $member_id, $points) {
    $member_id = (int)$member_id;
    $points    = (int)$points;
    mysqli_query($conn, "UPDATE members SET karma = karma + $points WHERE id = $member_id");
}

function getKarmaLevel($karma) {
    if ($karma >= 50) return ['label' => 'Champion',   'color' => 'danger',  'icon' => '🏆'];
    if ($karma >= 25) return ['label' => 'Trusted',    'color' => 'warning', 'icon' => '⭐'];
    if ($karma >= 10) return ['label' => 'Contributor','color' => 'success', 'icon' => '🌿'];
    return                    ['label' => 'Newcomer',  'color' => 'secondary','icon' => '🌱'];
}

function getNextMilestone($karma) {
    if ($karma < 10) return ['target' => 10,  'label' => 'Contributor — unlock blog posting'];
    if ($karma < 25) return ['target' => 25,  'label' => 'Trusted — get a trusted badge'];
    if ($karma < 50) return ['target' => 50,  'label' => 'Champion — top community member'];
    return null;
}

function canPostBlog($karma) {
    return $karma >= 10;
}
?>
<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !in_array($_SESSION['role'], ['volunteer', 'shelter', 'admin'])) {
    echo json_encode([]);
    exit;
}

$rescue_id = isset($_GET['rescue_id']) ? (int)$_GET['rescue_id'] : 0;
$after     = isset($_GET['after'])     ? (int)$_GET['after']     : 0;
$member_id = (int)$_SESSION['member_id'];

if (!$rescue_id) { echo json_encode([]); exit; }

$result = mysqli_query($conn, "
    SELECT rm.id, rm.sender_id, rm.message, rm.created_at, m.full_name, m.role
    FROM rescue_messages rm
    JOIN members m ON rm.sender_id = m.id
    WHERE rm.rescue_id = $rescue_id AND rm.id > $after
    ORDER BY rm.created_at ASC
");

$out = [];
while ($msg = mysqli_fetch_assoc($result)) {
    $is_mine   = $msg['sender_id'] == $member_id;
    $role_map  = ['admin' => '#6f42c1', 'volunteer' => '#198754', 'shelter' => '#0d6efd', 'user' => '#6c757d'];
    $color     = $role_map[$msg['role']] ?? '#6c757d';

    $meta  = '<span style="background:' . $color . ';color:#fff;font-size:0.65rem;padding:0.2em 0.5em;border-radius:6px;margin-right:4px;">'
           . ucfirst(htmlspecialchars($msg['role'])) . '</span>';
    $meta .= '<strong>' . htmlspecialchars($msg['full_name']) . '</strong> · ';
    $meta .= timeAgo($msg['created_at']);

    $out[] = [
        'id'           => $msg['id'],
        'sender_id'    => $msg['sender_id'],
        'message_html' => nl2br(htmlspecialchars($msg['message'])),
        'meta_html'    => $meta,
    ];
}

echo json_encode($out);

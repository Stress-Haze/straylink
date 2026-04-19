<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$role      = $_SESSION['role'];
$member_id = (int)$_SESSION['member_id'];

// Only volunteers, shelters, and admins can access
if (!in_array($role, ['volunteer', 'shelter', 'admin'])) {
    redirect('../index.php');
}

$error   = '';
$success = '';

// ── Post a message ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rescue_id'], $_POST['message'])) {
    $rescue_id = (int)$_POST['rescue_id'];
    $message   = trim($_POST['message']);

    if ($message !== '') {
        $msg_safe = mysqli_real_escape_string($conn, $message);
        mysqli_query($conn, "INSERT INTO rescue_messages (rescue_id, sender_id, message)
            VALUES ($rescue_id, $member_id, '$msg_safe')");

        // Notify admin + anyone who has previously messaged in this thread (except sender)
        $participants = mysqli_query($conn, "
            SELECT DISTINCT sender_id FROM rescue_messages
            WHERE rescue_id = $rescue_id AND sender_id != $member_id
        ");
        $notified = [];
        while ($p = mysqli_fetch_assoc($participants)) {
            addNotification($conn, $p['sender_id'], 'rescue_message',
                'New message in a rescue report thread.',
                'rescue_board.php?report=' . $rescue_id . '#chat');
            $notified[] = $p['sender_id'];
        }
        // Always notify admin if not already notified and not the sender
        $admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM members WHERE role='admin' LIMIT 1"));
        if ($admin && !in_array($admin['id'], $notified) && $admin['id'] != $member_id) {
            addNotification($conn, $admin['id'], 'rescue_message',
                'New message in a rescue report thread.',
                'rescue_board.php?report=' . $rescue_id . '#chat');
        }
    }
    redirect('rescue_board.php?report=' . $rescue_id . '#chat');
}

// ── Admin: resolve a report ─────────────────────────────────────────────────
if ($role === 'admin' && isset($_GET['resolve']) && is_numeric($_GET['resolve'])) {
    $rid = (int)$_GET['resolve'];
    mysqli_query($conn, "UPDATE rescue_reports SET status='resolved', resolved_by=$member_id, resolved_at=NOW() WHERE id=$rid");
    notifyRoles($conn, ['volunteer', 'shelter'], 'rescue_resolved',
        'A rescue report has been marked as resolved.',
        'rescue_board.php');
    redirect('rescue_board.php');
}

// ── Admin: reopen a report ──────────────────────────────────────────────────
if ($role === 'admin' && isset($_GET['reopen']) && is_numeric($_GET['reopen'])) {
    $rid = (int)$_GET['reopen'];
    mysqli_query($conn, "UPDATE rescue_reports SET status='approved', resolved_by=NULL, resolved_at=NULL WHERE id=$rid");
    redirect('rescue_board.php');
}

// ── Admin: delete a report ──────────────────────────────────────────────────
if ($role === 'admin' && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $rid = (int)$_GET['delete'];
    // Delete associated messages first
    mysqli_query($conn, "DELETE FROM rescue_messages WHERE rescue_id = $rid");
    // Delete the report
    mysqli_query($conn, "DELETE FROM rescue_reports WHERE id = $rid");
    redirect('rescue_board.php');
}

// ── Fetch open report for chat view ────────────────────────────────────────
$active_report = null;
$messages      = [];
if (isset($_GET['report']) && is_numeric($_GET['report'])) {
    $rid           = (int)$_GET['report'];
    $active_report = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT r.*, m.full_name AS reporter_name
        FROM rescue_reports r
        JOIN members m ON r.reported_by = m.id
        WHERE r.id = $rid AND r.status IN ('approved','in_progress','resolved')
    "));
    if ($active_report) {
        $msg_result = mysqli_query($conn, "
            SELECT rm.*, m.full_name, m.role
            FROM rescue_messages rm
            JOIN members m ON rm.sender_id = m.id
            WHERE rm.rescue_id = $rid
            ORDER BY rm.created_at ASC
        ");
        while ($msg = mysqli_fetch_assoc($msg_result)) {
            $messages[] = $msg;
        }
    }
}

// ── Fetch all approved/in_progress/resolved reports ────────────────────────
$filter  = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'open';
$status_filter = $filter === 'resolved' ? "'resolved'" : "'approved','in_progress'";

$reports = mysqli_query($conn, "
    SELECT r.*, m.full_name AS reporter_name,
        (SELECT COUNT(*) FROM rescue_messages rm WHERE rm.rescue_id = r.id) AS msg_count
    FROM rescue_reports r
    JOIN members m ON r.reported_by = m.id
    WHERE r.status IN ($status_filter)
    ORDER BY r.created_at DESC
");

$report_list = [];
while ($row = mysqli_fetch_assoc($reports)) {
    $report_list[] = $row;
}

// Depth for includes
$nav_depth = 1;
$active_page = 'rescue_board';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rescue Board — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-card { cursor: pointer; transition: all 0.2s; border-left: 4px solid transparent; }
        .report-card:hover { border-left-color: var(--green); background: #f8fdf9; }
        .report-card.active-report { border-left-color: var(--green); background: #f0fdf4; }
        .urgency-critical { border-left-color: #dc3545 !important; }
        .urgency-high     { border-left-color: #ffc107 !important; }
        .chat-box { height: 380px; overflow-y: auto; background: #f8faf6; border-radius: 12px; padding: 1rem; }
        .chat-bubble { max-width: 75%; margin-bottom: 0.75rem; }
        .chat-bubble.mine { margin-left: auto; }
        .chat-bubble .bubble-inner {
            padding: 0.6rem 0.9rem;
            border-radius: 16px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .chat-bubble.mine .bubble-inner  { background: var(--green); color: #fff; border-bottom-right-radius: 4px; }
        .chat-bubble.theirs .bubble-inner { background: #fff; border: 1px solid var(--cream-border); border-bottom-left-radius: 4px; }
        .chat-meta { font-size: 0.75rem; color: var(--text-light); margin-top: 0.2rem; }
        .chat-bubble.mine .chat-meta { text-align: right; }
        .board-layout { display: grid; grid-template-columns: 340px 1fr; gap: 1.5rem; align-items: start; }
        @media (max-width: 991px) { .board-layout { grid-template-columns: 1fr; } }
        .role-badge-admin     { background: #6f42c1; color: #fff; }
        .role-badge-volunteer { background: #198754; color: #fff; }
        .role-badge-shelter   { background: #0d6efd; color: #fff; }
        .role-badge-user      { background: #6c757d; color: #fff; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container py-4">

    <?php
    $back_url = 'home.php';
    if ($role === 'admin') {
        $back_url = '../dashboard/admin/index.php';
    } elseif ($role === 'volunteer') {
        $back_url = '../dashboard/volunteer/index.php';
    } elseif ($role === 'shelter') {
        $back_url = '../dashboard/shelter/index.php';
    }
    ?>
    <a href="<?= $back_url ?>" class="btn btn-success btn-sm mb-4">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>

    <div class="mb-4">
        <div class="mb-3">
            <p class="section-kicker mb-1"><i class="bi bi-broadcast text-success me-1"></i>Community Rescue Board</p>
            <h2 class="fw-bold mb-0">Active Rescue Reports</h2>
            <p class="text-muted small mb-0">Approved reports open for coordination between volunteers, shelters, and admin.</p>
        </div>
        <div class="d-grid gap-2 d-sm-flex gap-sm-2">
            <a href="?filter=open"     class="btn btn-sm <?= $filter !== 'resolved' ? 'btn-success' : 'btn-outline-success' ?>"><i class="bi bi-hourglass-split me-1"></i>Open</a>
            <a href="?filter=resolved" class="btn btn-sm <?= $filter === 'resolved' ? 'btn-success' : 'btn-outline-success' ?>"><i class="bi bi-check-circle me-1"></i>Resolved</a>
        </div>
    </div>

    <?php if (empty($report_list)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-check-circle" style="font-size:3rem;display:block;margin-bottom:1rem;"></i>
            <h5><?= $filter === 'resolved' ? 'No resolved reports yet.' : 'No open rescue reports right now.' ?></h5>
        </div>
    <?php else: ?>
    <div class="board-layout">

        <!-- LEFT: Report list -->
        <div>
            <?php foreach ($report_list as $r): ?>
                <?php
                    $is_active = $active_report && $active_report['id'] == $r['id'];
                    $urg_class = $r['urgency'] === 'critical' ? 'urgency-critical' : ($r['urgency'] === 'high' ? 'urgency-high' : '');
                    $urg_badge = $r['urgency'] === 'critical' ? 'danger' : ($r['urgency'] === 'high' ? 'warning' : ($r['urgency'] === 'medium' ? 'info' : 'secondary'));
                ?>
                <a href="?report=<?= $r['id'] ?>&filter=<?= $filter ?>#chat" class="text-decoration-none">
                    <div class="card mb-2 report-card <?= $urg_class ?> <?= $is_active ? 'active-report' : '' ?>">
                        <div class="card-body py-3 px-3">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <strong class="fs-6"><?= htmlspecialchars($r['title']) ?></strong>
                                <span class="badge bg-<?= $urg_badge ?> flex-shrink-0"><?= ucfirst($r['urgency']) ?></span>
                            </div>
                            <div class="text-muted small mb-2"><?= htmlspecialchars(substr($r['description'], 0, 80)) ?>...</div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($r['location_label'] ?? '—') ?>
                                </small>
                                <small class="text-muted">
                                    <i class="bi bi-chat me-1"></i><?= $r['msg_count'] ?>
                                    &nbsp;·&nbsp;
                                    <?= timeAgo($r['created_at']) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- RIGHT: Chat panel -->
        <div>
        <?php if ($active_report): ?>
            <div class="card shadow-sm" id="chat">
                <div class="card-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($active_report['title']) ?></h5>
                        <div class="text-muted small">
                            Reported by <strong><?= htmlspecialchars($active_report['reporter_name']) ?></strong>
                            · <?= date('M d, Y', strtotime($active_report['created_at'])) ?>
                            <?php if ($active_report['location_label']): ?>
                                · <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($active_report['location_label']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($active_report['photo']): ?>
                            <a href="../public/uploads/<?= $active_report['photo'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-image me-1"></i>Photo
                            </a>
                        <?php endif; ?>
                        <?php if ($role === 'admin'): ?>
                            <?php if ($active_report['status'] !== 'resolved'): ?>
                                <a href="?resolve=<?= $active_report['id'] ?>" class="btn btn-sm btn-success"
                                   onclick="return confirm('Mark this rescue as resolved?')">
                                    <i class="bi bi-check-circle me-1"></i>Mark Resolved
                                </a>
                            <?php else: ?>
                                <div class="d-flex gap-2">
                                    <a href="?reopen=<?= $active_report['id'] ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen
                                    </a>
                                    <a href="?delete=<?= $active_report['id'] ?>" class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this resolved report? This cannot be undone.')">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($role === 'shelter' && $active_report['status'] === 'resolved'): ?>
                            <a href="../dashboard/shelter/animal_add.php?from_rescue=<?= $active_report['id'] ?>" class="btn btn-sm btn-success">
                                <i class="bi bi-plus-circle me-1"></i>Add Animal to Shelter
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <div class="card-body border-bottom pb-3">
                    <p class="mb-2 text-muted small fw-bold text-uppercase" style="letter-spacing:.06em;">Report Details</p>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($active_report['description'])) ?></p>
                    <?php if ($active_report['status'] === 'resolved'): ?>
                        <div class="alert alert-success mt-3 mb-0 py-2">
                            <i class="bi bi-check-circle me-1"></i>
                            This rescue has been marked as <strong>resolved</strong>
                            <?= $active_report['resolved_at'] ? 'on ' . date('M d, Y', strtotime($active_report['resolved_at'])) : '' ?>.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Chat messages -->
                <div class="card-body border-bottom">
                    <p class="mb-2 text-muted small fw-bold text-uppercase" style="letter-spacing:.06em;">
                        <i class="bi bi-chat-dots me-1"></i>Coordination Thread
                    </p>
                    <div class="chat-box" id="chatBox">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-4 small">No messages yet. Be the first to update the team.</div>
                        <?php endif; ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php $is_mine = $msg['sender_id'] == $member_id; ?>
                            <div class="chat-bubble <?= $is_mine ? 'mine' : 'theirs' ?>">
                                <div class="bubble-inner"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                <div class="chat-meta">
                                    <span class="badge badge-sm role-badge-<?= $msg['role'] ?> me-1" style="font-size:0.65rem;padding:0.2em 0.5em;border-radius:6px;">
                                        <?= ucfirst($msg['role']) ?>
                                    </span>
                                    <strong><?= htmlspecialchars($msg['full_name']) ?></strong> · <?= timeAgo($msg['created_at']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Message input -->
                <?php if ($active_report['status'] !== 'resolved'): ?>
                <div class="card-body">
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="rescue_id" value="<?= $active_report['id'] ?>">
                        <textarea name="message" class="form-control" rows="2"
                            placeholder="Update the team — location, condition, ETA..." required
                            style="resize:none;"></textarea>
                        <button type="submit" class="btn btn-success px-3">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="card-body text-center text-muted small py-3">
                    This report is resolved. Thread is now read-only.
                </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-arrow-left-circle" style="font-size:2.5rem;display:block;margin-bottom:1rem;"></i>
                    <p class="mb-0">Select a report on the left to view details and join the coordination thread.</p>
                </div>
            </div>
        <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-scroll chat to bottom on load
const chatBox = document.getElementById('chatBox');
if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

<?php if ($active_report && $active_report['status'] !== 'resolved'): ?>
// Poll for new messages every 10 seconds
setInterval(function() {
    fetch('rescue_board_messages.php?rescue_id=<?= $active_report['id'] ?>&after=<?= empty($messages) ? 0 : end($messages)['id'] ?>')
        .then(r => r.json())
        .then(data => {
            if (data.length > 0) {
                data.forEach(msg => {
                    const isMine = msg.sender_id == <?= $member_id ?>;
                    const div = document.createElement('div');
                    div.className = 'chat-bubble ' + (isMine ? 'mine' : 'theirs');
                    div.innerHTML = `
                        <div class="bubble-inner">${msg.message_html}</div>
                        <div class="chat-meta">${msg.meta_html}</div>
                    `;
                    chatBox.appendChild(div);
                });
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
}, 10000);
<?php endif; ?>
</script>
</body>
</html>

<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Ensure db.php establishes $conn

$user_id = $_SESSION['user_id'];

// Fetch user email for invite matching
$stmt_email = $conn->prepare("SELECT email FROM users WHERE id = ?");
$user_email = '';
if ($stmt_email) {
    $stmt_email->bind_param("i", $user_id);
    $stmt_email->execute();
    $stmt_email->bind_result($user_email);
    $stmt_email->fetch();
    $stmt_email->close();
}

// Handle invite response (accept/decline)
if (isset($_GET['action'], $_GET['invite_id']) && is_numeric($_GET['invite_id'])) {
    $invite_id = (int) $_GET['invite_id'];
    $action = $_GET['action'];
    
    // Fetch invite to verify ownership
    $stmt_inv = $conn->prepare("SELECT team_id FROM invites WHERE invite_id = ? AND invitee_email = ? AND status = 'pending'");
    if ($stmt_inv) {
        $stmt_inv->bind_param("is", $invite_id, $user_email);
        $stmt_inv->execute();
        $stmt_inv->bind_result($team_id_for_invite);
        $fetched = $stmt_inv->fetch();
        $stmt_inv->close();

        if ($fetched) {
            if ($action === 'accept') {
                // Add user to team
                $stmt_add = $conn->prepare("INSERT INTO team_members (team_id, user_id) VALUES (?, ?)");
                if ($stmt_add) {
                    $stmt_add->bind_param("ii", $team_id_for_invite, $user_id);
                    $stmt_add->execute();
                    $stmt_add->close();
                    $resp_status = 'accepted';
                    $_SESSION['team_action_success'] = "You have joined the team successfully.";
                } else {
                    $_SESSION['team_action_error'] = "Error adding to team: " . $conn->error;
                    $resp_status = null;
                }
            } elseif ($action === 'decline') {
                $resp_status = 'declined';
                $_SESSION['team_action_success'] = "Invitation declined.";
            }
            if (isset($resp_status)) {
                $stmt_upd = $conn->prepare("UPDATE invites SET status = ?, responded_at = NOW() WHERE invite_id = ?");
                if ($stmt_upd) {
                    $stmt_upd->bind_param("si", $resp_status, $invite_id);
                    $stmt_upd->execute();
                    $stmt_upd->close();
                }
            }
        } else {
            $_SESSION['team_action_error'] = "Invite not found or already responded.";
        }
    } else {
        $_SESSION['team_action_error'] = "Database error: " . $conn->error;
    }
    header("Location: my_teams.php");
    exit();
}

// Fetch pending invites for display
$invites = [];
if ($user_email) {
    $stmt_upcoming = $conn->prepare(
        "SELECT i.invite_id, t.id AS team_id, t.name AS team_name, u.name AS inviter_name
         FROM invites i
         JOIN teams t ON i.team_id = t.id
         JOIN users u ON i.inviter_id = u.id
         WHERE i.invitee_email = ? AND i.status = 'pending'");
    if ($stmt_upcoming) {
        $stmt_upcoming->bind_param("s", $user_email);
        $stmt_upcoming->execute();
        $result_inv = $stmt_upcoming->get_result();
        $invites = $result_inv->fetch_all(MYSQLI_ASSOC);
        $stmt_upcoming->close();
    }
}

// Fetch teams the user is a member of
$stmt_teams = $conn->prepare(
    "SELECT t.id, t.name, t.description, t.creator_id, tm.role
     FROM teams t
     JOIN team_members tm ON t.id = tm.team_id
     WHERE tm.user_id = ?
     ORDER BY t.name"
);
$stmt_teams->bind_param("i", $user_id);
$stmt_teams->execute();
$teams_result = $stmt_teams->get_result();
$teams = $teams_result->fetch_all(MYSQLI_ASSOC);
$stmt_teams->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Teams | Smart Time Manager</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background-color: #f4f4f4; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        .container { max-width: 900px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; color: #333; }
        .message { padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .message.success { background-color: #dff0d8; color: #3c763d; }
        .message.error { background-color: #f2dede; color: #a94442; }
        .invite-box { background: #fff3cd; border:1px solid #ffeeba; padding: 15px; border-radius:4px; margin-bottom:20px; }
        .invite-box p { margin: 0 0 10px; color: #856404; }
        .invite-box a { margin-right:10px; text-decoration:none; font-weight:bold; }
        .invite-box a.accept { color: #155724; }
        .invite-box a.decline { color: #721c24; }
        .team-list { list-style: none; padding: 0; margin: 0; }
        .team-item { background-color: #f9f9f9; border:1px solid #eee; border-radius:6px; padding:16px; margin-bottom:16px;
                     display:flex; justify-content: space-between; align-items:center; flex-wrap:wrap; }
        .team-info { flex:1; min-width:200px; }
        .team-name { font-size:1.4rem; font-weight:600; color:#2c3e50; }
        .team-description { color:#7f8c8d; font-size:0.95rem; margin-top:6px; }
        .admin-label { background:#5cb85c; color:#fff; padding:4px 8px; border-radius:4px; font-size:0.8rem; margin-left:8px; }
        .team-actions { text-align: right; }
        .team-actions a, .team-actions button { margin-left:8px; padding:8px 12px; font-size:0.9rem; border:none; border-radius:4px; cursor:pointer; }
        .team-actions a.manage { background:#4A90E2; color:#fff; text-decoration:none; }
        .team-actions button.leave { background:#d9534f; color:#fff; }
        .team-actions button.delete { background:#c9302c; color:#fff; }
        .team-actions a.manage:hover, .team-actions button:hover { opacity:0.9; }
        .actions-footer { text-align:center; margin-top:30px; }
        .actions-footer a { display:inline-block; margin:0 10px; padding:10px 20px; border:1px solid #4A90E2; border-radius:4px; color:#4A90E2; text-decoration:none; }
        .actions-footer a:hover { background:#e6f2ff; }
    </style>
</head>
<body>
    <div class="container">
        <h2>My Teams</h2>

        <?php if (isset($_SESSION['team_action_error'])): ?>
            <div class="message error"><?php echo htmlspecialchars($_SESSION['team_action_error']); unset($_SESSION['team_action_error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['team_action_success'])): ?>
            <div class="message success"><?php echo htmlspecialchars($_SESSION['team_action_success']); unset($_SESSION['team_action_success']); ?></div>
        <?php endif; ?>

        <?php if ($invites): ?>
            <h3>Pending Invitations</h3>
            <?php foreach ($invites as $inv): ?>
                <div class="invite-box">
                    <p>Invitation to <strong><?php echo htmlspecialchars($inv['team_name']); ?></strong> from <?php echo htmlspecialchars($inv['inviter_name']); ?>.</p>
                    <a class="accept" href="my_teams.php?action=accept&invite_id=<?php echo $inv['invite_id']; ?>">Accept</a>
                    <a class="decline" href="my_teams.php?action=decline&invite_id=<?php echo $inv['invite_id']; ?>">Decline</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($teams)): ?>
            <p class="no-teams" style="text-align:center; color:#7f8c8d; margin-top:40px;">You are not currently a member of any teams.</p>
        <?php else: ?>
            <ul class="team-list">
                <?php foreach ($teams as $team): ?>
                    <li class="team-item">
                        <div class="team-info">
                            <div class="team-name">
                                <?php echo htmlspecialchars($team['name']); ?>
                                <?php if ($team['role'] === 'admin'): ?><span class="admin-label">Admin</span><?php endif; ?>
                            </div>
                            <?php if (!empty($team['description'])): ?><div class="team-description"><?php echo htmlspecialchars($team['description']); ?></div><?php endif; ?>
                        </div>
                        <div class="team-actions">
                            <a class="manage" href="manage_team.php?id=<?php echo $team['id']; ?>">Manage</a>
                            <?php if ($team['role'] === 'admin' && $team['creator_id'] === $user_id): ?>
                                <button class="delete" form="delete-<?php echo $team['id']; ?>">Delete</button>
                            <?php else: ?>
                                <button class="leave" form="leave-<?php echo $team['id']; ?>">Leave</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($team['role'] === 'admin' && $team['creator_id'] === $user_id): ?>
                            <form id="delete-<?php echo $team['id']; ?>" method="post" action="delete_team.php" style="display:none;">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                            </form>
                        <?php else: ?>
                            <form id="leave-<?php echo $team['id']; ?>" method="post" action="leave_team.php" style="display:none;">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="actions-footer">
            <a href="create_team.php">Create New Team</a>
            <a href="index.php">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>

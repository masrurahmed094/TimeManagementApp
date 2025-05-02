<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Ensure db.php establishes $conn

$user_id = $_SESSION['user_id'];

// Fetch teams the user is a member of, including the creator_id
$stmt_teams = $conn->prepare("SELECT t.id, t.name, t.description, t.creator_id, tm.role
                               FROM teams t
                               JOIN team_members tm ON t.id = tm.team_id
                               WHERE tm.user_id = ?
                               ORDER BY t.name");
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
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        .team-list {
            list-style: none;
            padding: 0;
        }
        .team-item {
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }
        .team-info {
            flex-grow: 1;
            margin-right: 15px; /* Add space between info and actions */
        }
        .team-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .team-description {
            color: #777;
            font-size: 0.9em;
            margin-top: 3px;
        }
        .team-actions {
             white-space: nowrap; /* Prevent buttons from wrapping */
             margin-top: 5px; /* Add some space if info wraps */
        }
        .team-actions form {
             display: inline-block; /* Keep forms side-by-side */
             margin-left: 10px;
        }

        .team-actions a,
        .team-actions button {
            display: inline-block;
            padding: 8px 12px;
            text-decoration: none;
            background-color: #4A90E2; /* Blue for Manage */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            vertical-align: middle; /* Align items nicely */
        }
         .team-actions a {
             margin-left: 0; /* No margin needed if it's the first item */
         }
        .team-actions a:hover,
        .team-actions button:hover {
            opacity: 0.9;
        }
        .admin-label {
            background-color: #5cb85c; /* Green */
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 5px;
            vertical-align: middle;
        }
        .leave-btn, .delete-btn {
            background-color: #d9534f; /* Red */
        }
        .leave-btn:hover, .delete-btn:hover {
            background-color: #c9302c;
        }
         .delete-btn {
             background-color: #d9534f; /* Explicitly red for delete */
         }

        .no-teams {
            text-align: center;
            color: #777;
            margin-top: 20px;
        }
        .create-team-link {
            display: block;
            margin: 20px auto;
            text-align: center;
            color: #4A90E2;
            text-decoration: none;
            width: fit-content; /* Make the link width fit its content */
            padding: 10px 15px;
            border: 1px solid #4A90E2;
            border-radius: 4px;
        }
        .create-team-link:hover {
             background-color: #f0f8ff; /* Light blue background on hover */
            text-decoration: none;
        }
        .back-link {
            display: block;
            margin-top: 15px;
            text-align: center;
            color: #555;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        /* Message Styles */
        .message {
             padding: 10px;
             border-radius: 4px;
             margin-bottom: 15px;
             text-align: center;
             color: white;
        }
        .message.success {
             background-color: #5cb85c; /* Green */
        }
        .message.error {
            background-color: #d9534f; /* Red */
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>My Teams</h2>

        <?php
        // Display messages from session or GET parameters
        if (isset($_SESSION['team_action_error'])) {
            echo '<div class="message error">' . htmlspecialchars($_SESSION['team_action_error']) . '</div>';
            unset($_SESSION['team_action_error']);
        }
        if (isset($_SESSION['leave_team_error'])) { // From original leave logic
             echo '<div class="message error">' . htmlspecialchars($_SESSION['leave_team_error']) . '</div>';
             unset($_SESSION['leave_team_error']);
        }
        if (isset($_GET['deleted']) && $_GET['deleted'] === 'true') {
            echo '<div class="message success">Team successfully deleted.</div>';
        }
        if (isset($_GET['left']) && $_GET['left'] === 'true') {
            echo '<div class="message success">You have successfully left the team.</div>';
        }
        ?>

        <?php if (empty($teams)): ?>
            <p class="no-teams">You are not currently a member of any teams.</p>
        <?php else: ?>
            <ul class="team-list">
                <?php foreach ($teams as $team): ?>
                    <li class="team-item">
                        <div class="team-info">
                            <div class="team-name">
                                <?php echo htmlspecialchars($team['name']); ?>
                                <?php if ($team['role'] === 'admin'): ?>
                                    <span class="admin-label">Admin</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($team['description'])): ?>
                                <div class="team-description"><?php echo htmlspecialchars($team['description']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="team-actions">
                            <a href="manage_team.php?id=<?php echo $team['id']; ?>">Manage</a>

                            <?php // Show Delete button only if user is the creator_id AND admin
                            if ($team['role'] === 'admin' && $team['creator_id'] === $user_id): ?>
                                <form method="post" action="delete_team.php" onsubmit="return confirm('WARNING: Deleting this team is permanent and cannot be undone. Are you sure?')">
                                    <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                    <button type="submit" class="delete-btn">Delete Team</button>
                                </form>
                            <?php // Show Leave button only if user is NOT the creator admin
                            elseif (!($team['role'] === 'admin' && $team['creator_id'] === $user_id)): ?>
                                <form method="post" action="leave_team.php">
                                    <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                    <button type="submit" class="leave-btn" onclick="return confirm('Are you sure you want to leave this team?')">Leave</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a href="create_team.php" class="create-team-link">Create New Team</a>
        <a href="index.php" class="back-link">Back to Dashboard</a>
    </div>
</body>
</html>

<?php
// NOTE: The PHP block that handled leaving the team POST request
// has been REMOVED from here. It should be placed in leave_team.php
?>
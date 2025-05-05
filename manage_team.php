<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Include your database connection

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_teams.php");
    exit();
}

$team_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];
// Initialize success and error variables (these will be populated from session on redirect)
$error = "";
$success = "";

// --- Message Handling from Session ---
if (isset($_SESSION['team_message'])) {
    $message = $_SESSION['team_message'];
    $message_type = $_SESSION['team_message_type'] ?? 'success'; // Default to success
    if ($message_type === 'error') {
        $error = $message;
    } else {
        $success = $message;
    }
    unset($_SESSION['team_message'], $_SESSION['team_message_type']);
}
// --- End Message Handling ---

// Fetch team details and check if the current user is an admin
$stmt_team = $conn->prepare("SELECT t.name, t.description, tm.role
                            FROM teams t
                            JOIN team_members tm ON t.id = tm.team_id
                            WHERE t.id = ? AND tm.user_id = ?");
$is_admin = false;
if ($stmt_team) {
    $stmt_team->bind_param("ii", $team_id, $user_id);
    if ($stmt_team->execute()) {
        $team_result = $stmt_team->get_result();
        if ($team_result->num_rows === 0) {
            header("Location: my_teams.php?error=not_member");
            exit();
        }
        $team = $team_result->fetch_assoc();
        $is_admin = ($team['role'] === 'admin');
    } else {
        $error = "Database error fetching team: " . $stmt_team->error;
    }
    $stmt_team->close();
} else {
    $error = "Database error preparing team fetch: " . $conn->error;
}

// Fetch team members
$members = [];
if (!empty($team)) {
    $stmt_members = $conn->prepare("SELECT u.id, u.name, tm.role
                                   FROM users u
                                   JOIN team_members tm ON u.id = tm.user_id
                                   WHERE tm.team_id = ?
                                   ORDER BY tm.role DESC, u.name");
    if ($stmt_members) {
        $stmt_members->bind_param("i", $team_id);
        if ($stmt_members->execute()) {
            $members = $stmt_members->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = $error ?: "Error fetching members: " . $stmt_members->error;
        }
        $stmt_members->close();
    } else {
        $error = $error ?: "Database error preparing members fetch: " . $conn->error;
    }
}

// Handle inviting a new member (via invite table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_member']) && $is_admin) {
    $invite_email = trim($_POST['invite_email']);

    if (empty($invite_email)) {
        $_SESSION['team_message'] = "Please enter an email address.";
        $_SESSION['team_message_type'] = 'error';
    } elseif (!filter_var($invite_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['team_message'] = "Invalid email format.";
        $_SESSION['team_message_type'] = 'error';
    } else {
        // Check for existing pending invite
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM invites WHERE team_id = ? AND invitee_email = ? AND status = 'pending'");
        if ($stmt_check) {
            $stmt_check->bind_param("is", $team_id, $invite_email);
            $stmt_check->execute();
            $pending = $stmt_check->get_result()->fetch_row()[0];
            $stmt_check->close();

            if ($pending > 0) {
                $_SESSION['team_message'] = "An invitation is already pending for this email.";
                $_SESSION['team_message_type'] = 'error';
            } else {
                $stmt_inv = $conn->prepare("INSERT INTO invites (team_id, inviter_id, invitee_email) VALUES (?, ?, ?)");
                if ($stmt_inv) {
                    $stmt_inv->bind_param("iis", $team_id, $user_id, $invite_email);
                    if ($stmt_inv->execute()) {
                        $_SESSION['team_message'] = "Invitation sent to $invite_email.";
                        $_SESSION['team_message_type'] = 'success';
                    } else {
                        $_SESSION['team_message'] = "Error sending invitation: " . $stmt_inv->error;
                        $_SESSION['team_message_type'] = 'error';
                    }
                    $stmt_inv->close();
                } else {
                    $_SESSION['team_message'] = "Database error preparing invite: " . $conn->error;
                    $_SESSION['team_message_type'] = 'error';
                }
            }
        } else {
            $_SESSION['team_message'] = "Database error checking invites: " . $conn->error;
            $_SESSION['team_message_type'] = 'error';
        }
    }
    header("Location: manage_team.php?id=$team_id");
    exit();
}

    // Handle assigning a new task
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_task']) && $is_admin) {
        $assigned_user_id = $_POST['assigned_user_id'] ?? null;
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $due_date = $_POST['due_date'];
        $priority = $_POST['priority'];

        if (empty($assigned_user_id) || empty($title) || empty($due_date) || empty($priority)) {
            $_SESSION['team_message'] = "Please fill in all required task fields.";
            $_SESSION['team_message_type'] = 'error';
        } else {
            $stmt_check_is_member = $conn->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND user_id = ?");
            if ($stmt_check_is_member) {
                $stmt_check_is_member->bind_param("ii", $team_id, $assigned_user_id);
                if ($stmt_check_is_member->execute()) {
                    $is_member = $stmt_check_is_member->get_result()->fetch_row()[0] > 0;
                    $stmt_check_is_member->close();

                    if (!$is_member) {
                        $_SESSION['team_message'] = "The selected user is not a member of this team.";
                        $_SESSION['team_message_type'] = 'error';
                    } else {
                        $stmt_insert_task = $conn->prepare("INSERT INTO tasks (user_id, team_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt_insert_task) {
                            $stmt_insert_task->bind_param("iissss", $assigned_user_id, $team_id, $title, $description, $due_date, $priority);
                            if ($stmt_insert_task->execute()) {
                                $_SESSION['team_message'] = "Task assigned successfully!";
                                $_SESSION['team_message_type'] = 'success';
                            } else {
                                $_SESSION['team_message'] = "Error assigning task: " . $stmt_insert_task->error;
                                $_SESSION['team_message_type'] = 'error';
                            }
                            $stmt_insert_task->close();
                        } else {
                            $_SESSION['team_message'] = "Database error preparing task assignment: " . $conn->error;
                            $_SESSION['team_message_type'] = 'error';
                        }
                    }
                } else {
                     $_SESSION['team_message'] = "Database error checking membership for task assignment: " . $stmt_check_is_member->error;
                     $_SESSION['team_message_type'] = 'error';
                     $stmt_check_is_member->close();
                }
            } else {
                $_SESSION['team_message'] = "Database error preparing membership check for task assignment: " . $conn->error;
                $_SESSION['team_message_type'] = 'error';
            }
        }

        header("Location: manage_team.php?id=" . $team_id);
        exit();
    }

    // Handle removing a team task
    if (isset($_GET['delete_team_task_id']) && is_numeric($_GET['delete_team_task_id']) && $is_admin) {
        $task_to_delete_id = $_GET['delete_team_task_id'];

        // Validate that the task belongs to this team for security
        $stmt_check_task_team = $conn->prepare("SELECT COUNT(*) FROM tasks WHERE id = ? AND team_id = ?");
        if ($stmt_check_task_team) {
            $stmt_check_task_team->bind_param("ii", $task_to_delete_id, $team_id);
            if ($stmt_check_task_team->execute()) {
                $is_task_in_team = $stmt_check_task_team->get_result()->fetch_row()[0] > 0;
                $stmt_check_task_team->close();

                if ($is_task_in_team) {
                    // Delete the task
                    $stmt_delete_task = $conn->prepare("DELETE FROM tasks WHERE id = ?");
                    if ($stmt_delete_task) {
                        $stmt_delete_task->bind_param("i", $task_to_delete_id);
                        if ($stmt_delete_task->execute()) {
                            $_SESSION['team_message'] = "Team task removed successfully!";
                            $_SESSION['team_message_type'] = 'success';
                        } else {
                            $_SESSION['team_message'] = "Error removing team task: " . $stmt_delete_task->error;
                            $_SESSION['team_message_type'] = 'error';
                        }
                        $stmt_delete_task->close();
                    } else {
                        $_SESSION['team_message'] = "Database error preparing team task removal: " . $conn->error;
                        $_SESSION['team_message_type'] = 'error';
                    }
                } else {
                    $_SESSION['team_message'] = "Task not found in this team.";
                    $_SESSION['team_message_type'] = 'error';
                }
            } else {
                $_SESSION['team_message'] = "Database error verifying team task ownership: " . $stmt_check_task_team->error;
                $_SESSION['team_message_type'] = 'error';
                $stmt_check_task_team->close();
            }
        } else {
            $_SESSION['team_message'] = "Database error preparing team task ownership check: " . $conn->error;
            $_SESSION['team_message_type'] = 'error';
        }

        header("Location: manage_team.php?id=" . $team_id);
        exit();
    }


    // Handle removing a member
    if (isset($_GET['remove_user']) && is_numeric($_GET['remove_user']) && $is_admin) {
        $user_to_remove_id = $_GET['remove_user'];

        if ($user_to_remove_id == $user_id) {
            $_SESSION['team_message'] = "You cannot remove yourself from the team.";
            $_SESSION['team_message_type'] = 'error';
        } else {
             // Start a transaction for removing a user and potentially deleting the team
             $conn->begin_transaction();
             try {
                 // Check if the user to remove is the creator and the sole admin
                $stmt_check_removed_user_role = $conn->prepare("SELECT t.creator_id, tm.role FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE t.id = ? AND tm.user_id = ?");
                $is_removed_user_creator_admin = false;

                if ($stmt_check_removed_user_role) {
                    $stmt_check_removed_user_role->bind_param("ii", $team_id, $user_to_remove_id);
                    if ($stmt_check_removed_user_role->execute()) {
                        $removed_user_info = $stmt_check_removed_user_role->get_result()->fetch_assoc();
                        $stmt_check_removed_user_role->close();

                        if ($removed_user_info && $removed_user_info['role'] === 'admin' && $removed_user_info['creator_id'] === $user_to_remove_id) {
                             $is_removed_user_creator_admin = true;
                        }
                    } else {
                         throw new Exception("Database error checking removed user role: " . $stmt_check_removed_user_role->error);
                    }
                } else {
                    throw new Exception("Database error preparing removed user role check: " . $conn->error);
                }

                if ($is_removed_user_creator_admin) {
                    $stmt_other_admins_check = $conn->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND role = 'admin' AND user_id != ?");
                    $can_remove = true;

                    if ($stmt_other_admins_check) {
                         $stmt_other_admins_check->bind_param("ii", $team_id, $user_to_remove_id);
                         if ($stmt_other_admins_check->execute()) {
                            $other_admins_count_check = $stmt_other_admins_check->get_result()->fetch_row()[0];
                            $stmt_other_admins_check->close();

                            if ($other_admins_count_check === 0) {
                                 $_SESSION['team_message'] = "Cannot remove the team creator if they are the only admin. Ask them to transfer admin rights or delete the team.";
                                 $_SESSION['team_message_type'] = 'error';
                                 $can_remove = false;
                            }
                         } else {
                            throw new Exception("Database error executing other admins check: " . $stmt_other_admins_check->error);
                         }
                    } else {
                        throw new Exception("Database error preparing other admins check: " . $conn->error);
                    }

                    if ($can_remove) { // Only attempt removal if checks passed
                         $stmt_remove = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
                        if ($stmt_remove) {
                            $stmt_remove->bind_param("ii", $team_id, $user_to_remove_id);
                            if (!$stmt_remove->execute()) {
                                throw new Exception("Database error removing user from team members: " . $stmt_remove->error);
                            }
                            $stmt_remove->close();
                             // Set success message after successful removal
                            $_SESSION['team_message'] = "User removed successfully!";
                            $_SESSION['team_message_type'] = 'success';
                        } else {
                             throw new Exception("Database error preparing remove statement: " . $conn->error);
                        }
                    }

                } else { // If the user to remove is not the sole creator admin, proceed with removal
                    $stmt_remove = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
                    if ($stmt_remove) {
                        $stmt_remove->bind_param("ii", $team_id, $user_to_remove_id);
                        if (!$stmt_remove->execute()) {
                            throw new Exception("Database error removing user from team members: " . $stmt_remove->error);
                        }
                        $stmt_remove->close();
                         // Set success message after successful removal
                        $_SESSION['team_message'] = "User removed successfully!";
                        $_SESSION['team_message_type'] = 'success';
                    } else {
                         throw new Exception("Database error preparing remove statement: " . $conn->error);
                    }
                }

                 // --- NEW: Check if the team is empty after removal and delete it ---
                $stmt_check_members_count = $conn->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
                if ($stmt_check_members_count) {
                    $stmt_check_members_count->bind_param("i", $team_id);
                    if ($stmt_check_members_count->execute()) {
                        $remaining_members_count = $stmt_check_members_count->get_result()->fetch_row()[0];
                        $stmt_check_members_count->close();

                        if ($remaining_members_count === 0) {
                            // If no members left, delete the team
                            $stmt_delete_team = $conn->prepare("DELETE FROM teams WHERE id = ?");
                            if ($stmt_delete_team) {
                                $stmt_delete_team->bind_param("i", $team_id);
                                if (!$stmt_delete_team->execute()) {
                                    throw new Exception("Database error deleting empty team: " . $stmt_delete_team->error);
                                }
                                $stmt_delete_team->close();
                                // Set a specific message for team deletion
                                $_SESSION['team_message'] = "User removed successfully. The team was empty and has been deleted.";
                                $_SESSION['team_message_type'] = 'success';

                                // --- NEW: Redirect to my_teams.php if the team was deleted ---
                                $conn->commit(); // Commit the transaction before redirecting
                                header("Location: my_teams.php");
                                exit();
                                // --- END NEW ---

                            } else {
                                throw new Exception("Database error preparing team deletion: " . $conn->error);
                            }
                        } else {
                             // If there are still members, the standard removal message is already set
                        }
                    } else {
                         throw new Exception("Database error checking remaining members count: " . $stmt_check_members_count->error);
                    }
                } else {
                    throw new Exception("Database error preparing members count check: " . $conn->error);
                }
                // --- END NEW ---

                // If the team was not deleted, commit the transaction and redirect to manage_team
                $conn->commit();
                header("Location: manage_team.php?id=" . $team_id);
                exit();

             } catch (Exception $e) {
                // Rollback the transaction in case of an error
                $conn->rollback();
                $_SESSION['team_message'] = "Error removing user: " . $e->getMessage();
                $_SESSION['team_message_type'] = 'error';
                error_log("Error removing user from team (Team ID: $team_id, User ID: $user_to_remove_id): " . $e->getMessage()); // Log the error
                header("Location: manage_team.php?id=" . $team_id);
                exit();
             }

        } // End of check if removing self

        // Original redirect for removing self (now handled within the try/catch)
        // header("Location: manage_team.php?id=" . $team_id);
        // exit();
    }


    // Fetch tasks assigned within this team
    // Tasks are linked to the team via team_id and assigned to a member via user_id
    $stmt_team_tasks = $conn->prepare("SELECT t.id, t.title, t.description, t.due_date, t.priority, t.status, u.name AS assigned_to_name
                                      FROM tasks t
                                      JOIN users u ON t.user_id = u.id
                                      WHERE t.team_id = ?
                                      ORDER BY t.due_date ASC");
    $team_tasks = []; // Initialize array for team tasks
    if ($stmt_team_tasks) {
        $stmt_team_tasks->bind_param("i", $team_id);
        if ($stmt_team_tasks->execute()) {
            $team_tasks_result = $stmt_team_tasks->get_result();
            $team_tasks = $team_tasks_result->fetch_all(MYSQLI_ASSOC);
            $stmt_team_tasks->close();
        } else {
             if (empty($error)) {
                $error = "Database error fetching team tasks: " . $stmt_team_tasks->error;
             }
              $stmt_team_tasks->close(); // Close on execution error
        }
    } else {
         if (empty($error)) {
             $error = "Database error preparing team tasks fetch: " . $conn->error;
         }
    }


    $conn->close(); // Close the main connection at the end of the script

    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Manage Team: <?php echo htmlspecialchars($team['name'] ?? 'Loading Error'); ?> | Smart Time Manager</title>
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
                margin-bottom: 15px;
            }
            /* Styles for messages */
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
            /* Existing styles */
            .team-details {
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #eee;
                border-radius: 4px;
                background-color: #f9f9f9;
            }
            .team-name {
                font-size: 1.5em;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .team-description {
                color: #777;
                font-size: 0.9em;
            }
            .members-list {
                list-style: none;
                padding: 0;
            }
            .member-item {
                padding: 10px;
                margin-bottom: 8px;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap; /* Allow wrapping */
            }
            .member-info {
                flex-grow: 1;
                margin-right: 10px; /* Space between info and actions */
            }
            .member-name {
                font-weight: bold;
            }
            .member-role {
                color: #777;
                font-size: 0.9em;
                margin-left: 5px;
                padding: 3px 6px;
                border-radius: 4px;
                background-color: #e0e0e0;
            }
            .admin-role {
                background-color: #5cb85c;
                color: white;
            }
            .member-actions a {
                color: #d9534f;
                text-decoration: none;
                margin-left: 10px;
                font-size: 0.9em;
            }
            .member-actions a:hover {
                text-decoration: underline;
            }
            .admin-controls {
                margin-top: 20px;
                border-top: 1px solid #eee;
                padding-top: 15px;
            }
             .invite-form, .assign-task-form {
                margin-top: 15px; /* Adjusted margin */
                padding: 15px;
                border: 1px solid #eee;
                border-radius: 4px;
                background-color: #f9f9f9;
            }
            .invite-form label, .assign-task-form label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            .invite-form input[type="email"],
            .assign-task-form input[type="text"],
            .assign-task-form textarea,
            .assign-task-form input[type="date"],
            .assign_task-form select {
                width: 100%;
                padding: 8px;
                margin-bottom: 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-sizing: border-box;
            }
             .assign-task-form select {
                 width: auto; /* Allow select to be its natural width */
                 display: inline-block; /* Keep select in line */
                 margin-right: 10px;
             }
             .assign-task-form textarea {
                 min-height: 60px;
                 resize: vertical;
             }
            .invite-form button[type="submit"],
            .assign-task-form button[type="submit"] {
                background-color: #4A90E2;
                color: white;
                padding: 10px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 1em;
                width: auto;
            }
            .invite-form button[type="submit"]:hover,
            .assign-task-form button[type="submit"]:hover {
                background-color: #357abd;
            }
            .back-link {
                display: block;
                margin-top: 20px;
                text-align: center;
                color: #555;
                text-decoration: none;
            }
            .back-link:hover {
                text-decoration: underline;
            }

            .team-tasks-list {
                margin-top: 30px;
                border-top: 1px solid #eee;
                padding-top: 15px;
            }
            .team-tasks-list h3 {
                margin-bottom: 15px;
            }
            .team-task-item {
                background-color: #f9f9f9;
                border: 1px solid #eee;
                border-left: 5px solid #4A90E2; /* Blue border to distinguish */
                border-radius: 4px;
                padding: 10px;
                margin-bottom: 10px;
                display: flex; /* Use flexbox to align items */
                justify-content: space-between; /* Space out info and actions */
                align-items: center; /* Vertically align items */
                flex-wrap: wrap; /* Allow wrapping */
            }
             .team-task-item .task-info {
                 flex-grow: 1; /* Allow info to take up available space */
                 margin-right: 10px; /* Space between info and actions */
             }
             .team-task-item strong {
                 color: #333;
                 display: block; /* Make title a block element */
                 margin-bottom: 5px;
             }
             .team-task-item .assigned-to {
                 font-size: 0.9em;
                 color: #555;
                 margin-bottom: 5px;
                 display: block;
             }
             .team-task-item .task-details {
                 font-size: 0.9em;
                 color: #666;
             }
              .team-task-item .task-details span {
                  margin-right: 15px;
              }
               .team-task-item .priority-High { color: red; }
               .team-task-item .priority-Medium { color: orange; }
               .team-task-item .priority-Low { color: green; }

               .team-task-item .task-actions a {
                   color: #d9534f; /* Red for delete */
                   text-decoration: none;
                   font-size: 0.9em;
                   white-space: nowrap; /* Prevent wrapping */
               }
               .team-task-item .task-actions a:hover {
                   text-decoration: underline;
               }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Manage Team: <?php echo htmlspecialchars($team['name'] ?? 'Loading Error'); ?></h2>

            <?php // Display messages here ?>
            <?php if ($error): ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <?php if ($success): ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <div class="team-details">
                <div class="team-name"><?php echo htmlspecialchars($team['name'] ?? 'N/A'); ?></div>
                <?php if (!empty($team['description'] ?? '')): ?>
                    <div class="team-description"><?php echo htmlspecialchars($team['description']); ?></div>
                <?php endif; ?>
                <p>Your Role: <span class="<?php echo ($is_admin ? 'admin-role' : 'member-role'); ?>"><?php echo htmlspecialchars(ucfirst($team['role'] ?? 'N/A')); ?></span></p>
            </div>

            <h3>Team Members</h3>
            <?php if (empty($members)): ?>
                <p>No members in this team yet.</p>
            <?php else: ?>
                <ul class="members-list">
                    <?php foreach ($members as $member): ?>
                        <li class="member-item">
                            <div class="member-info">
                                <span class="member-name"><?php echo htmlspecialchars($member['name']); ?></span>
                                <span class="member-role <?php echo ($member['role'] === 'admin' ? 'admin-role' : ''); ?>"><?php echo htmlspecialchars(ucfirst($member['role'])); ?></span>
                            </div>
                            <?php if ($is_admin && $member['id'] !== $user_id): ?>
                                <div class="member-actions">
                                    <a href="manage_team.php?id=<?php echo $team_id; ?>&remove_user=<?php echo $member['id']; ?>" onclick="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($member['name']); ?> from this team?')">Remove</a>
                                    </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($is_admin): ?>
                <div class="admin-controls">
                    <h3>Admin Actions</h3>

                    <div class="invite-form">
                        <h3>Invite New Member</h3>
                        <form method="post">
                            <label for="invite_email">Email Address:</label>
                            <input type="email" id="invite_email" name="invite_email" required>
                            <button type="submit" name="invite_member">Invite Member</button>
                        </form>
                    </div>

                     <div class="assign-task-form">
                        <h3>Assign Task to Member</h3>
                        <form method="post">
                            <input type="hidden" name="assign_task" value="1">
                            <div>
                                <label for="assigned_user_id">Assign To:</label>
                                <select id="assigned_user_id" name="assigned_user_id" required>
                                    <option value="">-- Select Member --</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['name']); ?> (<?php echo htmlspecialchars(ucfirst($member['role'])); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="task_title">Task Title:</label>
                                <input type="text" id="task_title" name="title" required>
                            </div>
                             <div>
                                <label for="task_description">Description (Optional):</label>
                                <textarea id="task_description" name="description"></textarea>
                            </div>
                            <div>
                                <label for="task_due_date">Due Date:</label>
                                <input type="date" id="task_due_date" name="due_date" required>
                            </div>
                            <div>
                                <label for="task_priority">Priority:</label>
                                <select id="task_priority" name="priority" required>
                                    <option value="High">High</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="Low">Low</option>
                                </select>
                            </div>
                            <button type="submit">Assign Task</button>
                        </form>
                     </div>

                </div>

                <div class="team-tasks-list">
                    <h3>Tasks Assigned in this Team</h3>
                    <?php if (empty($team_tasks)): ?>
                        <p>No tasks have been assigned within this team yet.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($team_tasks as $task): ?>
                                <li class="team-task-item">
                                    <div class="task-info">
                                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                        <span class="assigned-to">Assigned to: <?php echo htmlspecialchars($task['assigned_to_name']); ?></span>
                                        <?php if (!empty($task['description'])): ?>
                                             <div class="task-details">Description: <?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
                                        <?php endif; ?>
                                        <div class="task-details">
                                             <span>Due: <?php echo htmlspecialchars($task['due_date']); ?></span>
                                             <span class="priority-<?php echo $task['priority']; ?>">Priority: <?php echo htmlspecialchars($task['priority']); ?></span>
                                             <span>Status: <?php echo htmlspecialchars(ucfirst($task['status'])); ?></span>
                                        </div>
                                    </div>
                                     <div class="task-actions">
                                         <?php // Add delete link for admins ?>
                                        <a href="manage_team.php?id=<?php echo $team_id; ?>&delete_team_task_id=<?php echo $task['id']; ?>" onclick="return confirm('Are you sure you want to remove this task?')">Remove</a>
                                     </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

            <?php endif; // End of is_admin check ?>


            <a href="my_teams.php" class="back-link">Back to My Teams</a>
        </div>
    </body>
    </html>
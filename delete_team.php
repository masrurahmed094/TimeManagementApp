<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Include your database connection

$user_id = $_SESSION['user_id'];
$team_id = $_POST['team_id'] ?? null;

// Using a unified session message system for feedback on my_teams.php
if (!$team_id) {
    $_SESSION['team_message'] = "Invalid team ID provided for deletion.";
    $_SESSION['team_message_type'] = 'error';
    header("Location: my_teams.php");
    exit();
}

// Start a transaction for atomicity
$conn->begin_transaction();

try {
    // --- Security Check: Verify user is the creator AND an admin of this team ---
    $stmt_check_creator_admin = $conn->prepare("SELECT t.creator_id, tm.role FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE t.id = ? AND tm.user_id = ?");
    $is_authorized_to_delete = false;

    if ($stmt_check_creator_admin) {
        $stmt_check_creator_admin->bind_param("ii", $team_id, $user_id);
        if ($stmt_check_creator_admin->execute()) {
            $result = $stmt_check_creator_admin->get_result();
            if ($result->num_rows > 0) {
                $team_info = $result->fetch_assoc();
                // User must be the creator AND have the 'admin' role in the team_members table
                if ($team_info['creator_id'] === $user_id && $team_info['role'] === 'admin') {
                    $is_authorized_to_delete = true;
                }
            }
            $stmt_check_creator_admin->close();
        } else {
            throw new Exception("Database error verifying team ownership and admin status: " . $stmt_check_creator_admin->error);
        }
    } else {
        throw new Exception("Database error preparing ownership/admin check: " . $conn->error);
    }
    // --- End Security Check ---

    if ($is_authorized_to_delete) {
        // Proceed with deletion if authorized
        $stmt_delete_team = $conn->prepare("DELETE FROM teams WHERE id = ?");
        if ($stmt_delete_team) {
            $stmt_delete_team->bind_param("i", $team_id);
            if ($stmt_delete_team->execute()) {
                // Check if a row was actually affected (team existed and was deleted)
                if ($stmt_delete_team->affected_rows > 0) {
                    $_SESSION['team_message'] = "Team successfully deleted.";
                    $_SESSION['team_message_type'] = 'success';
                } else {
                    // Team with this ID was not found or user wasn't the creator/admin (redundant due to check, but good fallback)
                     $_SESSION['team_message'] = "Team could not be deleted or was not found.";
                     $_SESSION['team_message_type'] = 'error';
                }
                 $stmt_delete_team->close();
            } else {
                throw new Exception("Database error deleting team: " . $stmt_delete_team->error);
            }
        } else {
            throw new Exception("Database error preparing team deletion: " . $conn->error);
        }

        $conn->commit(); // Commit the transaction

    } else {
        // If authorization failed
        $_SESSION['team_message'] = "You are not authorized to delete this team.";
        $_SESSION['team_message_type'] = 'error';
        $conn->rollback(); // Rollback if the check failed or wasn't authorized
    }


} catch (Exception $e) {
    // Rollback the transaction in case of any exception
    $conn->rollback();
    $_SESSION['team_message'] = "An error occurred during team deletion: " . $e->getMessage();
    $_SESSION['team_message_type'] = 'error';
    error_log("Error deleting team (ID: $team_id, User ID: $user_id): " . $e->getMessage()); // Log the error
}

$conn->close(); // Close the database connection

// Redirect back to the my_teams page to show the message
header("Location: my_teams.php");
exit();
?>
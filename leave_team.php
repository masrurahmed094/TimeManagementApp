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

// Using a unified session message system
if (!$team_id) {
    $_SESSION['team_message'] = "Invalid team ID.";
    $_SESSION['team_message_type'] = 'error';
    header("Location: my_teams.php");
    exit();
}

// Start a transaction to ensure data consistency
$conn->begin_transaction();

try {
    // Check if the user is the sole admin of the team and the creator
    // This prevents the creator-admin from leaving without deleting or transferring manually
    $stmt_check_creator_admin = $conn->prepare("SELECT t.creator_id, tm.role FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE t.id = ? AND tm.user_id = ?");
     $is_creator_admin = false;
    if ($stmt_check_creator_admin) {
        $stmt_check_creator_admin->bind_param("ii", $team_id, $user_id);
        if ($stmt_check_creator_admin->execute()) {
            $creator_admin_result = $stmt_check_creator_admin->get_result();
            if ($creator_admin_result->num_rows > 0) {
                $row = $creator_admin_result->fetch_assoc();
                if ($row['role'] === 'admin' && $row['creator_id'] === $user_id) {
                    $is_creator_admin = true;
                }
            }
             $stmt_check_creator_admin->close();
        } else {
            throw new Exception("Database error checking creator admin status: " . $stmt_check_creator_admin->error);
        }
    } else {
         throw new Exception("Database error preparing creator admin check: " . $conn->error);
    }


    if ($is_creator_admin) {
         // Check if there are other admins
        $stmt_other_admins = $conn->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND role = 'admin' AND user_id != ?");
         if ($stmt_other_admins) {
            $stmt_other_admins->bind_param("ii", $team_id, $user_id);
             if ($stmt_other_admins->execute()) {
                $other_admins_count = $stmt_other_admins->get_result()->fetch_row()[0];
                $stmt_other_admins->close();

                if ($other_admins_count === 0) {
                    $_SESSION['team_message'] = "You are the only admin and the creator of this team. Please delete the team or transfer admin rights before leaving.";
                    $_SESSION['team_message_type'] = 'error';
                    $conn->rollback();
                    header("Location: my_teams.php");
                    exit();
                }
             } else {
                 throw new Exception("Database error checking other admins: " . $stmt_other_admins->error);
             }
         } else {
             throw new Exception("Database error preparing other admins check: " . $conn->error);
         }
    }


    // Check the leaving user's role
    $stmt_check_role = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND user_id = ?");
     $current_role = null;
    if ($stmt_check_role) {
        $stmt_check_role->bind_param("ii", $team_id, $user_id);
         if ($stmt_check_role->execute()) {
            $role_result = $stmt_check_role->get_result();
            if ($role_result->num_rows > 0) {
                $current_role = $role_result->fetch_assoc()['role'];
            }
            $stmt_check_role->close();
         } else {
            throw new Exception("Database error checking user role: " . $stmt_check_role->error);
         }
    } else {
        throw new Exception("Database error preparing user role check: " . $conn->error);
    }


    if ($current_role === 'admin') {
        // If the leaving user is an admin, find the oldest remaining member
        $stmt_oldest_member = $conn->prepare("SELECT user_id FROM team_members WHERE team_id = ? AND user_id != ? ORDER BY joined_at ASC LIMIT 1");
         $oldest_member_id = null;
        if ($stmt_oldest_member) {
            $stmt_oldest_member->bind_param("ii", $team_id, $user_id);
             if ($stmt_oldest_member->execute()) {
                $oldest_member_result = $stmt_oldest_member->get_result();
                if ($oldest_member_result->num_rows > 0) {
                    $oldest_member_id = $oldest_member_result->fetch_assoc()['user_id'];
                }
                $stmt_oldest_member->close();
             } else {
                 throw new Exception("Database error finding oldest member: " . $stmt_oldest_member->error);
             }
        } else {
             throw new Exception("Database error preparing oldest member check: " . $conn->error);
        }


        if ($oldest_member_id) {
            // Transfer admin role to the oldest member
            $stmt_transfer_admin = $conn->prepare("UPDATE team_members SET role = 'admin' WHERE team_id = ? AND user_id = ?");
             if ($stmt_transfer_admin) {
                $stmt_transfer_admin->bind_param("ii", $team_id, $oldest_member_id);
                 if (!$stmt_transfer_admin->execute()) {
                    throw new Exception("Database error transferring admin role: " . $stmt_transfer_admin->error);
                 }
                 $stmt_transfer_admin->close();
             } else {
                 throw new Exception("Database error preparing admin transfer: " . $conn->error);
             }
        } else {
             // If no other members, the team will be admin-less. Log or handle as needed.
              error_log("Team ID $team_id is now without an admin after user $user_id left.");
        }
    }

    // Delete tasks assigned to the user in this team
    $stmt_delete_tasks = $conn->prepare("DELETE FROM tasks WHERE team_id = ? AND user_id = ?");
    if ($stmt_delete_tasks) {
        $stmt_delete_tasks->bind_param("ii", $team_id, $user_id);
        if (!$stmt_delete_tasks->execute()) {
            throw new Exception("Database error deleting user's tasks from team: " . $stmt_delete_tasks->error);
        }
        $stmt_delete_tasks->close();
    } else {
        throw new Exception("Database error preparing task deletion: " . $conn->error);
    }


    // Remove the leaving user from the team
    $stmt_leave_team = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
    if ($stmt_leave_team) {
        $stmt_leave_team->bind_param("ii", $team_id, $user_id);
         if (!$stmt_leave_team->execute()) {
            throw new Exception("Database error removing user from team members: " . $stmt_leave_team->error);
         }
         $stmt_leave_team->close();
    } else {
         throw new Exception("Database error preparing team member removal: " . $conn->error);
    }

    // --- NEW: Check if the team is empty and delete it ---
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
                     $_SESSION['team_message'] = "You have successfully left the team. The team was empty and has been deleted.";
                     $_SESSION['team_message_type'] = 'success';
                } else {
                    throw new Exception("Database error preparing team deletion: " . $conn->error);
                }
            } else {
                 // If there are still members, set the standard leave message
                 $_SESSION['team_message'] = "You have successfully left the team.";
                 $_SESSION['team_message_type'] = 'success';
            }
        } else {
            throw new Exception("Database error checking remaining members count: " . $stmt_check_members_count->error);
        }
    } else {
        throw new Exception("Database error preparing members count check: " . $conn->error);
    }
    // --- END NEW ---


    // Commit the transaction
    $conn->commit();

    // Redirect to my_teams.php after successful actions
    header("Location: my_teams.php");
    exit();

} catch (Exception $e) {
    // Rollback the transaction in case of an error
    $conn->rollback();
    $_SESSION['team_message'] = "Error leaving the team: " . $e->getMessage();
    $_SESSION['team_message_type'] = 'error';
    error_log("Error leaving team (ID: $team_id, User ID: $user_id): " . $e->getMessage()); // Log the error
    header("Location: my_teams.php");
    exit();
}

$conn->close();
?>
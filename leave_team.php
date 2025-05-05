<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$team_id = $_POST['team_id'] ?? null;

// Validate team ID
if (!$team_id) {
    $_SESSION['team_action_error'] = "Invalid team ID.";
    header("Location: my_teams.php");
    exit();
}

$conn->begin_transaction();
try {
    // Prevent sole creator-admin from leaving
    $stmt = $conn->prepare("SELECT t.creator_id, tm.role FROM teams t JOIN team_members tm ON t.id=tm.team_id WHERE t.id=? AND tm.user_id=?");
    $stmt->bind_param("ii", $team_id, $user_id);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($info['role']==='admin' && $info['creator_id']===$user_id) {
        $stmt2 = $conn->prepare("SELECT COUNT(*) FROM team_members WHERE team_id=? AND role='admin' AND user_id!=?");
        $stmt2->bind_param("ii", $team_id, $user_id);
        $stmt2->execute();
        $otherAdmins = $stmt2->get_result()->fetch_row()[0];
        $stmt2->close();
        if ($otherAdmins===0) {
            $_SESSION['team_action_error'] = "You are the sole creator-admin. Please transfer admin rights or delete the team first.";
            $conn->rollback();
            header("Location: my_teams.php");
            exit();
        }
    }

    // Delete user's tasks
    $stmt = $conn->prepare("DELETE FROM tasks WHERE team_id=? AND user_id=?");
    $stmt->bind_param("ii", $team_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Remove user from team
    $stmt = $conn->prepare("DELETE FROM team_members WHERE team_id=? AND user_id=?");
    $stmt->bind_param("ii", $team_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // If admin leaving, transfer to oldest
    if ($info['role']==='admin') {
        $stmt = $conn->prepare("SELECT user_id FROM team_members WHERE team_id=? ORDER BY joined_at ASC LIMIT 1");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $next = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($next) {
            $stmt = $conn->prepare("UPDATE team_members SET role='admin' WHERE team_id=? AND user_id=?");
            $stmt->bind_param("ii", $team_id, $next['user_id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Check empty team
    $stmt = $conn->prepare("SELECT COUNT(*) FROM team_members WHERE team_id=?");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    if ($count===0) {
        $stmt = $conn->prepare("DELETE FROM teams WHERE id=?");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['team_action_success'] = "You left the team; it was empty and has been deleted.";
    } else {
        $_SESSION['team_action_success'] = "You have successfully left the team.";
    }

    $conn->commit();
    header("Location: my_teams.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['team_action_error'] = "Error leaving team: " . $e->getMessage();
    header("Location: my_teams.php");
    exit();
}
?>
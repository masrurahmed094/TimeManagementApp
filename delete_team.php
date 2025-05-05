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

if (!$team_id) {
    $_SESSION['team_action_error'] = "Invalid team ID.";
    header("Location: my_teams.php");
    exit();
}

$conn->begin_transaction();
try {
    // Verify creator-admin
    $stmt = $conn->prepare("SELECT t.creator_id, tm.role FROM teams t JOIN team_members tm ON t.id=tm.team_id WHERE t.id=? AND tm.user_id=?");
    $stmt->bind_param("ii", $team_id, $user_id);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!($info['creator_id']===$user_id && $info['role']==='admin')) {
        $_SESSION['team_action_error'] = "You are not authorized to delete this team.";
        $conn->rollback();
        header("Location: my_teams.php");
        exit();
    }

    // Delete team
    $stmt = $conn->prepare("DELETE FROM teams WHERE id=?");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted) {
        $_SESSION['team_action_success'] = "Team successfully deleted.";
    } else {
        $_SESSION['team_action_error'] = "Team not found or already deleted.";
    }

    $conn->commit();
    header("Location: my_teams.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['team_action_error'] = "Error deleting team: " . $e->getMessage();
    header("Location: my_teams.php");
    exit();
}
?>

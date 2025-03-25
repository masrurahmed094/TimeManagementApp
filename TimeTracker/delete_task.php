<?php
session_start();
if (!isset($_SESSION["UserID"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["task_id"])) {
    $taskID = intval($_POST["task_id"]);
    $userID = $_SESSION["UserID"];

    $conn = new mysqli("localhost", "root", "", "csc4200");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Make sure the task belongs to the current user
    $checkStmt = $conn->prepare("SELECT TaskID FROM task WHERE TaskID = ? AND UserID = ?");
    $checkStmt->bind_param("ii", $taskID, $userID);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows === 1) {
        // Safe to delete
        $deleteStmt = $conn->prepare("DELETE FROM task WHERE TaskID = ?");
        $deleteStmt->bind_param("i", $taskID);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $checkStmt->close();
    $conn->close();
}

header("Location: my_tasks.php");
exit;

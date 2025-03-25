<?php
session_start();
if (!isset($_SESSION["UserID"])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "csc4200");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userID = $_SESSION["UserID"];
$taskID = intval($_POST["task_id"]);
$taskName = trim($_POST["task_name"]);
$category = trim($_POST["category"]);
$startTime = $_POST["start_time"];
$endTime = $_POST["end_time"];
$tagsRaw = trim($_POST["tags"]);

// Update task
$stmt = $conn->prepare("UPDATE task SET TaskName = ?, Category = ? WHERE TaskID = ? AND UserID = ?");
$stmt->bind_param("ssii", $taskName, $category, $taskID, $userID);
$stmt->execute();
$stmt->close();

// Update timeentry
$timeStmt = $conn->prepare("UPDATE timeentry SET StartTime = ?, EndTime = ? WHERE TaskID = ?");
$timeStmt->bind_param("ssi", $startTime, $endTime, $taskID);
$timeStmt->execute();
$timeStmt->close();

// Handle tags
$conn->query("DELETE FROM tasktag WHERE TaskID = $taskID"); // clear existing tags

$tags = array_filter(array_map('trim', explode(",", $tagsRaw)));
foreach ($tags as $tagName) {
    // Check if tag exists
    $tagID = null;
    $tagStmt = $conn->prepare("SELECT TagID FROM tag WHERE TagName = ?");
    $tagStmt->bind_param("s", $tagName);
    $tagStmt->execute();
    $tagStmt->bind_result($tagID);
    $tagStmt->fetch();
    $tagStmt->close();

    if (!$tagID) {
        $insertTag = $conn->prepare("INSERT INTO tag (TagName) VALUES (?)");
        $insertTag->bind_param("s", $tagName);
        $insertTag->execute();
        $tagID = $insertTag->insert_id;
        $insertTag->close();
    }

    // Link task to tag
    $linkStmt = $conn->prepare("INSERT INTO tasktag (TaskID, TagID) VALUES (?, ?)");
    $linkStmt->bind_param("ii", $taskID, $tagID);
    $linkStmt->execute();
    $linkStmt->close();
}

$conn->close();
header("Location: my_tasks.php");
exit;

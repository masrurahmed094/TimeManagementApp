<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION["UserID"])) {
    header("Location: login.php");
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "", "csc4200");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sanitize user input
$taskName = trim($_POST["task_name"]);
$category = trim($_POST["category"]);
$startTime = $_POST["start_time"];
$endTime = $_POST["end_time"];
$tagsRaw = trim($_POST["tags"]);
$userID = $_SESSION["UserID"];

// 1. Insert Task
$stmt = $conn->prepare("
    INSERT INTO task (UserID, TaskName, Category, retrospective)
    VALUES (?, ?, ?, 1)
");
$stmt->bind_param("iss", $userID, $taskName, $category);
if (!$stmt->execute()) {
    die("Error inserting task: " . $stmt->error);
}
$taskID = $stmt->insert_id;
$stmt->close();

// 2. Handle Tags
$tagIDs = [];
if (!empty($tagsRaw)) {
    $tags = array_map('trim', explode(',', $tagsRaw));
    foreach ($tags as $tagName) {
        // Check if tag exists
        $tagStmt = $conn->prepare("SELECT TagID FROM tag WHERE TagName = ?");
        $tagStmt->bind_param("s", $tagName);
        $tagStmt->execute();
        $tagStmt->store_result();

        if ($tagStmt->num_rows > 0) {
            $tagStmt->bind_result($tagID);
            $tagStmt->fetch();
        } else {
            // Insert new tag
            $insertTagStmt = $conn->prepare("INSERT INTO tag (TagName) VALUES (?)");
            $insertTagStmt->bind_param("s", $tagName);
            if ($insertTagStmt->execute()) {
                $tagID = $insertTagStmt->insert_id;
            } else {
                die("Error inserting tag: " . $insertTagStmt->error);
            }
            $insertTagStmt->close();
        }

        $tagIDs[] = $tagID;
        $tagStmt->close();
    }
}

// 3. Insert into tasktag
foreach ($tagIDs as $tagID) {
    $tasktagStmt = $conn->prepare("INSERT INTO tasktag (TaskID, TagID) VALUES (?, ?)");
    $tasktagStmt->bind_param("ii", $taskID, $tagID);
    if (!$tasktagStmt->execute()) {
        die("Error linking tag: " . $tasktagStmt->error);
    }
    $tasktagStmt->close();
}

// 4. Insert into timeentry
$timeStmt = $conn->prepare("
    INSERT INTO timeentry (TaskID, UserID, StartTime, EndTime)
    VALUES (?, ?, ?, ?)
");
$timeStmt->bind_param("iiss", $taskID, $userID, $startTime, $endTime);

if (!$timeStmt->execute()) {
    die("Error inserting time entry: " . $timeStmt->error);
}
$timeStmt->close();

$conn->close();
echo "✅ Task successfully created! <a href='dashboard.php'>Back to dashboard</a>";
?>

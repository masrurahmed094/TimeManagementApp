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

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["task_id"])) {
    $taskID = intval($_GET["task_id"]);

    // Fetch task and timeentry
    $stmt = $conn->prepare("
        SELECT t.TaskName, t.Category, te.StartTime, te.EndTime
        FROM task t
        JOIN timeentry te ON t.TaskID = te.TaskID
        WHERE t.TaskID = ? AND t.UserID = ?
    ");
    $stmt->bind_param("ii", $taskID, $userID);
    $stmt->execute();
    $stmt->bind_result($taskName, $category, $startTime, $endTime);
    $stmt->fetch();
    $stmt->close();

    // Fetch tags
    $tags = [];
    $tagStmt = $conn->prepare("
        SELECT TagName FROM tag
        JOIN tasktag ON tag.TagID = tasktag.TagID
        WHERE tasktag.TaskID = ?
    ");
    $tagStmt->bind_param("i", $taskID);
    $tagStmt->execute();
    $tagResult = $tagStmt->get_result();
    while ($row = $tagResult->fetch_assoc()) {
        $tags[] = $row["TagName"];
    }
    $tagString = implode(", ", $tags);
    $tagStmt->close();
}
?>

<h2>Edit Task</h2>
<form method="POST" action="update_task.php">
    <input type="hidden" name="task_id" value="<?= htmlspecialchars($taskID) ?>">
    Task Name: <input type="text" name="task_name" value="<?= htmlspecialchars($taskName) ?>" required><br><br>
    Category: <input type="text" name="category" value="<?= htmlspecialchars($category) ?>" required><br><br>
    Start Time: <input type="datetime-local" name="start_time" value="<?= date('Y-m-d\TH:i', strtotime($startTime)) ?>" required><br><br>
    End Time: <input type="datetime-local" name="end_time" value="<?= date('Y-m-d\TH:i', strtotime($endTime)) ?>" required><br><br>
    Tags (comma-separated): <input type="text" name="tags" value="<?= htmlspecialchars($tagString) ?>"><br><br>
    <input type="submit" value="Update Task">
</form>

<a href="my_tasks.php">⬅ Cancel</a>

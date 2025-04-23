<?php
session_start();
date_default_timezone_set('America/New_York'); //
$preset = $_GET['preset'] ?? null;
$filterStart = $_GET['start_date'] ?? null;
$filterEnd = $_GET['end_date'] ?? null;

if ($preset && !$filterStart && !$filterEnd) {
    $today = date("Y-m-d");

    switch ($preset) {
        case "today":
            $filterStart = $today;
            $filterEnd = $today;
            break;
        case "last3":
            $filterStart = date("Y-m-d", strtotime("-2 days")); // includes today
            $filterEnd = $today;
            break;
        case "month":
            $filterStart = date("Y-m-01");
            $filterEnd = $today;
            break;
        case "year":
            $filterStart = date("Y-01-01");
            $filterEnd = $today;
            break;
        case "all":
            $filterStart = null;
            $filterEnd = null;
            break;
    }
}


if (!isset($_SESSION["UserID"])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "csc4200");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userID = $_SESSION["UserID"];

// --- Handle optional date filter. Default to today.
$startDateTime = $filterStart ? $filterStart . " 00:00:00" : null;
$endDateTime = $filterEnd ? $filterEnd . " 23:59:59" : null;

$dateClause = "";
$params = [$userID];
$types = "i";

if ($startDateTime) {
    $dateClause .= " AND te.StartTime >= ?";
    $params[] = $startDateTime;
    $types .= "s";
}

if ($endDateTime) {
    $dateClause .= " AND te.EndTime <= ?";
    $params[] = $endDateTime;
    $types .= "s";
}


// --- Fetch tasks with optional date filter
$taskQuery = "
    SELECT 
        t.TaskID,
        t.TaskName,
        t.Category,
        te.StartTime,
        te.EndTime
    FROM task t
    JOIN timeentry te ON t.TaskID = te.TaskID
    WHERE t.UserID = ?" . $dateClause . "
    ORDER BY te.StartTime ASC
";



$stmt = $conn->prepare($taskQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$taskResult = $stmt->get_result();
?>

<h2>My Tasks</h2>


<!-- Date Range Filter -->
<form method="GET" action="">
    <label>Preset Range:</label>
    <select name="preset" onchange="this.form.submit()">
        <option value="">-- Select --</option>
        <option value="today" <?= ($_GET['preset'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="last3" <?= ($_GET['preset'] ?? '') === 'last3' ? 'selected' : '' ?>>Last 3 Days</option>
        <option value="month" <?= ($_GET['preset'] ?? '') === 'month' ? 'selected' : '' ?>>This Month</option>
        <option value="year" <?= ($_GET['preset'] ?? '') === 'year' ? 'selected' : '' ?>>This Year</option>
        <option value="all" <?= ($_GET['preset'] ?? '') === 'all' ? 'selected' : '' ?>>All Time</option>
    </select>

    <br><br>

    <label>Or choose a custom range:</label>
    Start Date: <input type="date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
    End Date: <input type="date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
    <input type="submit" value="Filter">
    <a href="my_tasks.php">Clear</a>
</form>


<br>

<table border="1" cellpadding="8">
    <tr>
        <th>Task Name</th>
        <th>Category</th>
        <th>Start Time</th>
        <th>End Time</th>
        <th>Time Elapsed</th>
        <th>Tags</th>
        <th>Actions</th>
    </tr>

<?php
while ($row = $taskResult->fetch_assoc()) {
    $taskID = $row["TaskID"];

    // --- Get tags for this task
    $tagQuery = "
        SELECT TagName FROM tag
        JOIN tasktag ON tag.TagID = tasktag.TagID
        WHERE tasktag.TaskID = ?
    ";
    $tagStmt = $conn->prepare($tagQuery);
    $tagStmt->bind_param("i", $taskID);
    $tagStmt->execute();
    $tagResult = $tagStmt->get_result();

    $tags = [];
    while ($tagRow = $tagResult->fetch_assoc()) {
        $tags[] = $tagRow["TagName"];
    }
    $tagStmt->close();

    //
    //
    //
    $startDateTime = $filterStart ? $filterStart . " 00:00:00" : null;
$endDateTime = $filterEnd ? $filterEnd . " 23:59:59" : null;

$dateClause = "";
$params = [$userID];
$types = "i";

if ($startDateTime) {
    $dateClause .= " AND te.StartTime >= ?";
    $params[] = $startDateTime;
    $types .= "s";
}

if ($endDateTime) {
    $dateClause .= " AND te.EndTime <= ?";
    $params[] = $endDateTime;
    $types .= "s";
}

    
    // --- Calculate time elapsed
    $start = new DateTime($row["StartTime"]);
    $end = new DateTime($row["EndTime"]);   
    
    
    $interval = $start->diff($end);
    $elapsed = $interval->format("%h hr %i min");
    
    
    
    

    // --- Display row
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row["TaskName"]) . "</td>";
    echo "<td>" . htmlspecialchars($row["Category"]) . "</td>";
    echo "<td>" . htmlspecialchars($row["StartTime"]) . "</td>";
    echo "<td>" . htmlspecialchars($row["EndTime"]) . "</td>";
    echo "<td>" . $elapsed . "</td>";
    echo "<td>" . htmlspecialchars(implode(", ", $tags)) . "</td>";
    echo "<td>
    <form method='GET' action='edit_task.php' style='display:inline'>
        <input type='hidden' name='task_id' value='$taskID'>
        <input type='submit' value='Edit'>
    </form>
    <form method='POST' action='delete_task.php' style='display:inline' onsubmit='return confirm(\"Are you sure?\")'>
        <input type='hidden' name='task_id' value='$taskID'>
        <input type='submit' value='Delete'>
    </form>
</td>"; 
   echo "</tr>";
}
$stmt->close();
$conn->close();
?>
</table>

<br>
<a href="dashboard.php">⬅ Back to Dashboard</a>

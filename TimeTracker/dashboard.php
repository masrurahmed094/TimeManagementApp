<?php
session_start();
if (!isset($_SESSION["UserID"])) {
    header("Location: login.php");
    exit;
}
?>

<h2>Welcome, <?php echo htmlspecialchars($_SESSION["Username"]); ?>!</h2>
<p>This is your dashboard.</p>



<h3>Retrospective Task Entry</h3>
<form method="POST" action="add_task.php">
    Task Name: <input type="text" name="task_name" required><br><br>
    Category: <input type="text" name="category" required><br><br>
    Start Time: <input type="datetime-local" name="start_time" required><br><br>
    End Time: <input type="datetime-local" name="end_time" required><br><br>
    Tags (comma-separated): <input type="text" name="tags"><br><br>
    <input type="submit" value="Add Task">
</form>
<br>
<a href="my_tasks.php">VIEW TASKS</a>
<br>
<br>

<a href="../planner/index.php">📅 Go to Planner/Scheduler</a>
<br>
<br>
<a href="logout.php">Logout</a>

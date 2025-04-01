<?php session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Task Creation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_task'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $title, $description, $due_date, $priority);
    $stmt->execute();

    header("Location: manage_tasks.php?added=true");
    exit();
}

// Handle Task Updating
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_all_tasks'])) {
    if (isset($_POST['tasks']) && is_array($_POST['tasks'])) {
        foreach ($_POST['tasks'] as $task_id => $task_data) {
            $title = $task_data['title'];
            $description = $task_data['description'];
            $due_date = $task_data['due_date'];
            $priority = $task_data['priority'];
            $status = $task_data['status'];

            $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssssssi", $title, $description, $due_date, $priority, $status, $task_id, $user_id);
            $stmt->execute();
        }
        header("Location: manage_tasks.php?updated=true");
        exit();
    }
}

// Handle Task Deletion
if (isset($_GET['delete_id'])) {
    $task_id = $_GET['delete_id'];

    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();

    header("Location: manage_tasks.php?deleted=true");
    exit();
}

// Sorting and Searching
$sort_by = $_GET['sort_by'] ?? 'due_date';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sort_clause = match ($sort_by) {
    'priority' => "ORDER BY FIELD(priority, 'High', 'Medium', 'Low')",
    'status'   => "ORDER BY FIELD(status, 'pending', 'completed')",
    default    => "ORDER BY due_date ASC",
};

$search_clause = $search !== '' ? "AND title LIKE ?" : '';
$sql = "SELECT * FROM tasks WHERE user_id=? $search_clause $sort_clause";

if ($search !== '') {
    $stmt = $conn->prepare($sql);
    $like_search = "%$search%";
    $stmt->bind_param("is", $user_id, $like_search);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$tasks = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Tasks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
        }
        header {
            background: #4A90E2;
            color: white;
            padding: 15px;
            text-align: center;
        }
        main {
            padding: 20px;
        }
        .success {
            background: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .layout {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .add-task, .task-list {
            flex: 1 1 300px;
        }
        input, textarea, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
        button {
            background: #4A90E2;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            width: 100%;
            box-sizing: border-box;
        }
        .task-item {
            padding: 10px;
            margin-bottom: 10px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .priority-High { border-left: 5px solid red; padding-left: 10px; }
        .priority-Medium { border-left: 5px solid orange; padding-left: 10px; }
        .priority-Low { border-left: 5px solid green; padding-left: 10px; }
        .task-controls {
            margin: 20px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .delete-btn {
            display: inline-block;
            background: red;
            color: white;
            text-align: center;
            padding: 8px;
            margin-top: 5px;
            text-decoration: none;
        }
        .task-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .task-filters input[type="text"] {
            flex: 1 1 200px;
        }
        .update-all-button-container {
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>

<header>
    <h1>Manage Tasks</h1>
    <a href="index.php" style="color: white; text-decoration: underline;"> Back to Dashboard</a>
</header>

<main>
    <?php if (isset($_GET['added'])) echo "<p class='success'>Task added successfully!</p>"; ?>
    <?php if (isset($_GET['updated'])) echo "<p class='success'>Tasks updated!</p>"; ?>
    <?php if (isset($_GET['deleted'])) echo "<p class='success'>Task deleted!</p>"; ?>

    <form method="get" class="task-filters">
        <select name="sort_by">
            <option value="due_date" <?php if ($sort_by == 'due_date') echo 'selected'; ?>>Sort by Due Date</option>
            <option value="priority" <?php if ($sort_by == 'priority') echo 'selected'; ?>>Sort by Priority</option>
            <option value="status" <?php if ($sort_by == 'status') echo 'selected'; ?>>Sort by Status</option>
        </select>

        <input type="text" name="search" placeholder="Search by Title" value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Apply</button>
    </form>

    <div class="layout">

        <div class="add-task">
            <h3>Add New Task</h3>
            <form method="post">
                <input type="text" name="title" placeholder="Task Title" required>
                <textarea name="description" placeholder="Task Description"></textarea>
                <input type="date" name="due_date" required>
                <select name="priority">
                    <option value="High">High</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="Low">Low</option>
                </select>
                <button type="submit" name="add_task">Add Task</button>
            </form>
        </div>

        <div class="task-list">
            <h3>Your Tasks</h3>
            <?php if ($tasks->num_rows > 0): ?>
                <form method="post">
                    <ul style="list-style-type: none; padding: 0;">
                        <?php while ($task = $tasks->fetch_assoc()): ?>
                            <li class="task-item priority-<?php echo $task['priority']; ?>">
                                <input type="hidden" name="tasks[<?php echo $task['id']; ?>][task_id]" value="<?php echo $task['id']; ?>">
                                <input type="text" name="tasks[<?php echo $task['id']; ?>][title]" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                                <textarea name="tasks[<?php echo $task['id']; ?>][description]"><?php echo htmlspecialchars($task['description']); ?></textarea>
                                <input type="date" name="tasks[<?php echo $task['id']; ?>][due_date]" value="<?php echo $task['due_date']; ?>" required>
                                <select name="tasks[<?php echo $task['id']; ?>][priority]">
                                    <option value="High" <?php if ($task['priority'] == 'High') echo 'selected'; ?>>High</option>
                                    <option value="Medium" <?php if ($task['priority'] == 'Medium') echo 'selected'; ?>>Medium</option>
                                    <option value="Low" <?php if ($task['priority'] == 'Low') echo 'selected'; ?>>Low</option>
                                </select>
                                <select name="tasks[<?php echo $task['id']; ?>][status]">
                                    <option value="pending" <?php if ($task['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="completed" <?php if ($task['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                                </select>
                                <a href="manage_tasks.php?delete_id=<?php echo $task['id']; ?>" class="delete-btn" onclick="return confirm('Delete this task?')">Delete</a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                    <div class="update-all-button-container">
                        <button type="submit" name="update_all_tasks">Update All Tasks</button>
                    </div>
                </form>
            <?php else: ?>
                <p>No tasks found<?php echo $search ? " for \"$search\"" : ""; ?>. Try again!</p>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>
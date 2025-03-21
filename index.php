<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle month/year from URL
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Normalize month/year
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$first_day_of_month = date('w', strtotime("$year-$month-01"));
$days_in_month = date('t', strtotime("$year-$month-01"));

// Task summary
$total_tasks = $conn->query("SELECT COUNT(*) AS total FROM tasks WHERE user_id='$user_id'")->fetch_assoc()['total'];
$completed_tasks = $conn->query("SELECT COUNT(*) AS total FROM tasks WHERE user_id='$user_id' AND status='completed'")->fetch_assoc()['total'];
$pending_tasks = $total_tasks - $completed_tasks;
$completion_percentage = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

// Fetch tasks
$task_query = $conn->query("SELECT title, description, due_date, priority, status FROM tasks WHERE user_id='$user_id' ORDER BY due_date ASC");

$tasks_by_date = [];
$reminders = [];
$today = date('Y-m-d');
$nearest_due_date = null;

while ($task = $task_query->fetch_assoc()) {
    $date = $task['due_date'];
    $tasks_by_date[$date][] = $task;

    if ($task['status'] === 'pending') {
        if (!$nearest_due_date || $date < $nearest_due_date) {
            $nearest_due_date = $date;
        }
    }
}

// Generate reminder list
if ($nearest_due_date) {
    foreach ($tasks_by_date as $date => $task_list) {
        if ($date > $nearest_due_date) break;
        foreach ($task_list as $t) {
            if ($t['status'] === 'pending') {
                $reminders[] = $t;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Smart Time Manager</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        header {
            background: #4A90E2;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header .manage-btn {
            background: black;
            color: red;
            border: 2px solid blue;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }
        main { padding: 20px; max-width: 960px; margin: auto; }

        .summary, .progress, .reminders, .calendar { margin-bottom: 30px; }

        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            height: 20px;
        }
        .progress-fill {
            height: 100%;
            text-align: center;
            color: white;
            font-size: 12px;
        }
        .reminders ul { list-style: none; padding: 0; }
        .reminders li { margin-bottom: 5px; }

        .calendar table {
            width: 100%;
            border-collapse: collapse;
        }
        .calendar th, .calendar td {
            border: 1px solid #ccc;
            width: 14.28%;
            height: 100px;
            vertical-align: top;
            padding: 5px;
            position: relative;
        }
        .calendar small {
            display: block;
            font-size: 11px;
            margin-top: 4px;
        }
        .calendar .High small { color: red; }
        .calendar .Medium small { color: orange; }
        .calendar .Low small { color: green; }

        .tooltip {
            position: absolute;
            background: #333;
            color: white;
            padding: 8px;
            font-size: 12px;
            border-radius: 5px;
            display: none;
            z-index: 999;
            max-width: 200px;
        }

        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .primary-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4A90E2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .completed-task {
            color: gray;
            text-decoration: line-through;
        }
    </style>
</head>
<body>

<header>
    <h1>Welcome, <?php echo $_SESSION['user_name']; ?>!</h1>
    <div>
        <a href="manage_tasks.php" class="manage-btn">Manage Tasks</a>
        <a href="logout.php" style="color: white; margin-left: 15px;">Logout</a>
    </div>
</header>

<main>

    <div class="summary">
        <h2>Task Summary</h2>
        <p>Total Tasks: <strong><?php echo $total_tasks; ?></strong></p>
        <p>Completed: <strong><?php echo $completed_tasks; ?></strong></p>
        <p>Pending: <strong><?php echo $pending_tasks; ?></strong></p>
    </div>

    <div class="progress">
        <h3>Progress</h3>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%; background-color: <?php
                echo ($completion_percentage >= 70) ? 'green' : (($completion_percentage >= 30) ? 'orange' : 'red');
            ?>;">
                <?php echo $completion_percentage; ?>%
            </div>
        </div>
    </div>

    <div class="reminders">
        <h3>Upcoming Reminders</h3>
        <?php if (count($reminders) > 0): ?>
            <ul>
                <?php foreach ($reminders as $r): ?>
                    <li>• <strong><?php echo $r['title']; ?></strong> - Due <?php echo date('M j', strtotime($r['due_date'])); ?> (<?php echo $r['priority']; ?>)</li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No upcoming reminders 🎉</p>
        <?php endif; ?>
    </div>

    <div class="calendar">
        <h3>
            <?php echo date('F Y', strtotime("$year-$month-01")); ?>
        </h3>

        <div class="nav-buttons">
            <a href="?month=<?php echo $month - 1; ?>&year=<?php echo $year; ?>">← Previous</a>
            <a href="?month=<?php echo $month + 1; ?>&year=<?php echo $year; ?>">Next →</a>
        </div>

        <table>
            <tr>
                <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                <th>Thu</th><th>Fri</th><th>Sat</th>
            </tr>
            <tr>
                <?php
                $day_counter = 0;

                for ($i = 0; $i < $first_day_of_month; $i++) {
                    echo "<td></td>";
                    $day_counter++;
                }

                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $tasks = $tasks_by_date[$current_date] ?? [];
                    $priority_class = '';
                    $tooltip_text = '';

                    if (!empty($tasks)) {
                        $priorities = array_column($tasks, 'priority');
                        if (in_array('High', $priorities)) $priority_class = 'High';
                        elseif (in_array('Medium', $priorities)) $priority_class = 'Medium';
                        else $priority_class = 'Low';

                        foreach (array_slice($tasks, 0, 3) as $t) {
                            $tooltip_text .= "• {$t['title']}\nPriority: {$t['priority']}\nStatus: {$t['status']}";
                            if ($t['description']) $tooltip_text .= "\n{$t['description']}";
                            $tooltip_text .= "\n\n";
                        }
                    }

                    echo "<td class='$priority_class' data-tooltip=\"" . htmlspecialchars(trim($tooltip_text)) . "\"><strong>$day</strong>";
                    if (!empty($tasks)) {
                        foreach ($tasks as $t) {
                            $status = $t['status'];
                            $class = ($status === 'completed') ? 'completed-task' : strtolower($t['priority']);
                            echo "<small class='$class'>{$t['title']}</small>";
                        }
                    }
                    echo "</td>";

                    $day_counter++;
                    if ($day_counter % 7 == 0) echo "</tr><tr>";
                }

                while ($day_counter % 7 != 0) {
                    echo "<td></td>";
                    $day_counter++;
                }
                ?>
            </tr>
        </table>
        <div class="tooltip" id="tooltip-box"></div>
    </div>

</main>

<script>
    const tooltip = document.getElementById('tooltip-box');
    const cells = document.querySelectorAll('[data-tooltip]');

    cells.forEach(cell => {
        cell.addEventListener('mouseover', e => {
            const text = cell.getAttribute('data-tooltip');
            if (!text.trim()) return;

            tooltip.style.display = 'block';
            tooltip.innerText = text.trim();
        });

        cell.addEventListener('mousemove', e => {
            tooltip.style.top = (e.pageY + 15) + 'px';
            tooltip.style.left = (e.pageX + 15) + 'px';
        });

        cell.addEventListener('mouseout', () => {
            tooltip.style.display = 'none';
        });
    });
</script>

</body>
</html>

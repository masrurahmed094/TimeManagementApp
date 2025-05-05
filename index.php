<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$user_id = $_SESSION['user_id'];

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$first_day_of_month = date('w', strtotime("$year-$month-01"));
$days_in_month = date('t', strtotime("$year-$month-01"));

$total_tasks = $conn->query("SELECT COUNT(*) AS total FROM tasks WHERE user_id='$user_id'")->fetch_assoc()['total'];
$completed_tasks = $conn->query("SELECT COUNT(*) AS total FROM tasks WHERE user_id='$user_id' AND status='completed'")->fetch_assoc()['total'];
$pending_tasks = $total_tasks - $completed_tasks;
$completion_percentage = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

$task_query = $conn->query("SELECT id, title, description, due_date, scheduled_date, priority, status FROM tasks WHERE user_id='$user_id' ORDER BY due_date ASC");

$tasks_by_date = [];
$upcoming_tasks = [];
$today = date('Y-m-d');

while ($task = $task_query->fetch_assoc()) {
    $date = $task['scheduled_date'] ?: $task['due_date'];
    $formatted = date('Y-m-d', strtotime($date));
    $tasks_by_date[$formatted][] = $task;

    if ($task['status'] === 'pending' && strtotime($formatted) >= strtotime($today)) {
        $upcoming_tasks[] = $task;
    }
}

usort($upcoming_tasks, function ($a, $b) {
    $a_date = $a['scheduled_date'] ?: $a['due_date'];
    $b_date = $b['scheduled_date'] ?: $b['due_date'];
    return strtotime($a_date) - strtotime($b_date);
});

$reminders = array_slice($upcoming_tasks, 0, 10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />

    <meta charset="UTF-8">
    <title>Dashboard | Smart Time Manager</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

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

        main {
            padding: 20px;
            max-width: 960px;
            margin: auto;
        }

        .summary, .progress, .reminders, .calendar {
            margin-bottom: 30px;
        }

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

        .reminders ul {
            list-style: none;
            padding: 0;
        }

        .reminders li {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reminders li span {
            flex-grow: 1;
        }

        .reminders li button {
            background-color: #5cb85c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .reminders li button:hover {
            background-color: #4cae4c;
        }

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

        .calendar .High small {
            color: red;
        }

        .calendar .Medium small {
            color: orange;
        }

        .calendar .Low small {
            color: green;
        }

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

        .today {
            background-color: #d9f7be !important;
            border: 2px solid #52c41a;
        }

        .time-now {
            display: block;
            font-size: 10px;
            margin-top: 4px;
            color: #595959;
        }
    </style>
</head>
<body>

<header>
    <h1>Welcome, <?php echo $_SESSION['user_name']; ?>!</h1>
    <div>
        <a href="my_teams.php" style="color: white; margin-right: 15px;">My Teams</a>
        <a href="manage_tasks.php" class="manage-btn" style="margin-right: 15px;">Manage Tasks</a>
        <a href="logout.php" style="color: white;">Logout</a>
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
            ?>;"><?php echo $completion_percentage; ?>%</div>
        </div>
    </div>

    <div class="reminders">
        <h3>Upcoming Reminders</h3>
        <?php if (count($reminders) > 0): ?>
            <ul>
                <?php foreach ($reminders as $r): ?>
                    <li>
                        <span>• <strong><?php echo $r['title']; ?></strong> - Due <?php echo date('M j', strtotime($r['due_date'])); ?> (<?php echo $r['priority']; ?>)</span>
                        <button onclick="scheduleTask('<?php echo $r['id']; ?>','<?php echo $r['title']; ?>', '<?php echo $r['due_date']; ?>', '<?php echo $r['scheduled_date']; ?>')">
                            <?php echo $r['scheduled_date'] ? 'Reschedule' : 'Schedule Time'; ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($upcoming_tasks) > count($reminders)): ?>
                <p><a href="manage_tasks.php">View All Upcoming Tasks</a></p>
            <?php endif; ?>
        <?php else: ?>
            <p>No upcoming pending tasks 🎉</p>
        <?php endif; ?>
    </div>


    <div class="calendar">
        <h3><?php echo date('F Y', strtotime("$year-$month-01")); ?></h3>
        <div class="nav-buttons">
            <a href="?month=<?php echo $month - 1; ?>&year=<?php echo $year; ?>">← Previous</a>
            <a href="?month=<?php echo $month + 1; ?>&year=<?php echo $year; ?>">Next →</a>
        </div>
        <table>
            <tr>
                <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
            </tr>
            <tr>
                <?php
                $day_counter = 0;
                $today_date = date('Y-m-d');

                for ($i = 0; $i < $first_day_of_month; $i++) {
                    echo "<td></td>";
                    $day_counter++;
                }

                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $tasks = $tasks_by_date[$current_date] ?? [];
                    $priority_class = '';
                    $tooltip_text = '';
                    $is_today = ($current_date === $today_date);

                    if (!empty($tasks)) {
                        $priorities = array_column($tasks, 'priority');
                        if (in_array('High', $priorities)) $priority_class = 'High';
                        elseif (in_array('Medium', $priorities)) $priority_class = 'Medium';
                        else $priority_class = 'Low';

                        foreach (array_slice($tasks, 0, 3) as $t) {
                            $tooltip_text .= "• {$t['title']}\n";
                            if ($t['scheduled_date']) $tooltip_text .= "Scheduled: " . date('M j, Y H:i', strtotime($t['scheduled_date'])) . "\n";
                            $tooltip_text .= "Due: " . date('M j, Y', strtotime($t['due_date'])) . "\n";
                            $tooltip_text .= "Priority: {$t['priority']}\nStatus: {$t['status']}";
                            if ($t['description']) $tooltip_text .= "\n{$t['description']}";
                            $tooltip_text .= "\n\n";
                        }
                    }

                    $cell_classes = trim("$priority_class" . ($is_today ? ' today' : ''));
                    echo "<td class='$cell_classes' data-tooltip=\"" . htmlspecialchars(trim($tooltip_text)) . "\"><strong>$day</strong>";

                    foreach ($tasks as $t) {
                        $status = $t['status'];
                        $class = ($status === 'completed') ? 'completed-task' : strtolower($t['priority']);
                        $display_date = $t['scheduled_date'] ? date('H:i', strtotime($t['scheduled_date'])) : date('H:i', strtotime($t['due_date']));
                        echo "<small class='$class'>{$t['title']} ($display_date)</small>";
                    }

                    if ($is_today) echo "<span class='time-now' id='local-time'></span>";
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

const nowSpan = document.getElementById('local-time');
if (nowSpan) {
    function updateLocalTime() {
        const now = new Date();
        nowSpan.textContent = `Now: ${now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true })}`;
    }
    updateLocalTime();
    setInterval(updateLocalTime, 60000);
}

function scheduleTask(taskId, title, dueDate, existingScheduled = '') {
    const defaultDate = existingScheduled || dueDate;

    let scheduledDate = prompt(`Enter the new scheduled date and time for "${title}" (Due: ${dueDate}):`, defaultDate);
    if (!scheduledDate) return alert("Task scheduling cancelled.");

    // Convert to ISO format for parsing
    const isoString = scheduledDate.replace(' ', 'T');
    let parsedDate = new Date(isoString);

    if (isNaN(parsedDate.getTime())) {
        return alert("Invalid format. Please use: YYYY-MM-DD HH:MM:SS");
    }

    const now = new Date();
    now.setSeconds(0, 0); // clean milliseconds/seconds for comparison

    if (parsedDate.getTime() < now.getTime()) {
        return alert("Scheduled time must be now or in the future.");
    }

    const dueDateObj = new Date(dueDate);
    if (parsedDate.getTime() > dueDateObj.getTime()) {
        return alert("Scheduled time cannot be after the due date.");
    }

    // Send time in local format instead of UTC
    const local = `${parsedDate.getFullYear()}-${String(parsedDate.getMonth()+1).padStart(2, '0')}-${String(parsedDate.getDate()).padStart(2, '0')} ${String(parsedDate.getHours()).padStart(2, '0')}:${String(parsedDate.getMinutes()).padStart(2, '0')}:00`;

    if (confirm(`Schedule "${title}" for ${local}?`)) {
        fetch('schedule_task.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `taskId=${taskId}&scheduledDate=${encodeURIComponent(local)}`
        })
        .then(res => res.text())
        .then(alert)
        .then(() => window.location.reload())
        .catch(() => alert('Error scheduling task.'));
    }
}



</script>
</body>
</html>

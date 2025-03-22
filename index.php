

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

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />

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
                    $cell_classes = trim("$priority_class" . ($is_today ? ' today' : ''));

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

                        $cell_classes = trim("$priority_class" . ($is_today ? ' today' : ''));
                    }

                    echo "<td class='$cell_classes' data-tooltip=\"" . htmlspecialchars(trim($tooltip_text)) . "\"><strong>$day</strong>";

                    if (!empty($tasks)) {
                        foreach ($tasks as $t) {
                            $status = $t['status'];
                            $class = ($status === 'completed') ? 'completed-task' : strtolower($t['priority']);
                            echo "<small class='$class'>{$t['title']}</small>";
                        }
                    }

                    if ($is_today) {
                        echo "<span class='time-now' id='local-time'></span>";
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

    // Show local system time in the today cell
    const nowSpan = document.getElementById('local-time');
    if (nowSpan) {
        function updateLocalTime() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            nowSpan.textContent = `Now: ${hours}:${minutes} ${ampm}`;
        }

        updateLocalTime();
        setInterval(updateLocalTime, 60000);
    }
</script>

</body>
</html>

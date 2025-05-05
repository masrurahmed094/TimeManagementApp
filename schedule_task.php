<?php
include 'db.php'; // Include your database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $taskId = $_POST['taskId'];
    $scheduledDate = $_POST['scheduledDate'];

    // Check if the database connection is valid
    if ($conn) {
        // Use prepared statements to prevent SQL injection
        $query = "UPDATE tasks SET scheduled_date = ? WHERE id = ?";
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param("si", $scheduledDate, $taskId); // "si" for string, integer
            if ($stmt->execute()) {
                echo "Task scheduled successfully and added to your calendar.";
            } else {
                echo "Error scheduling task: " . $stmt->error; // Use $stmt->error
            }
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $conn->error; // Use $conn->error
        }
        $conn->close(); // Close the connection
    } else {
        echo "Error connecting to database.";
    }
} else {
    echo "Invalid request.";
}
?>

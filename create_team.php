<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Ensure db.php establishes a connection named $conn

$error = "";
$success = "";
$user_id = $_SESSION['user_id']; // Get user_id from session

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $team_name = trim($_POST['team_name']);
    $team_description = trim($_POST['team_description']);

    // Validate team name
    if (empty($team_name)) {
        $error = "Team name is required.";
    } elseif (strlen($team_name) > 255) {
        $error = "Team name cannot exceed 255 characters.";
    } else {
        // Check if team name already exists (prevent duplicates)
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM teams WHERE name = ?");
        $stmt_check->bind_param("s", $team_name);
        $stmt_check->execute();
        $count = $stmt_check->get_result()->fetch_row()[0];
        $stmt_check->close();

        if ($count > 0) {
            $error = "A team with that name already exists.";
        }
    }


    if (empty($error)) {
        // Insert the new team, including the creator_id
        $stmt_insert_team = $conn->prepare("INSERT INTO teams (name, description, creator_id) VALUES (?, ?, ?)");
        // Bind the creator_id (user_id from session)
        $stmt_insert_team->bind_param("ssi", $team_name, $team_description, $user_id);

        if ($stmt_insert_team->execute()) {
            $team_id = $conn->insert_id; // Get the ID of the newly created team

            // Add the creating user as admin to the team_members table
            $stmt_add_admin = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'admin')");
            $stmt_add_admin->bind_param("ii", $team_id, $user_id);
            if ($stmt_add_admin->execute()) {
                $success = "Team created successfully!";
            } else {
                $error = "Error adding you as admin to the team. Please contact support.";
                // Rollback: Delete the team if adding the admin fails
                $conn->query("DELETE FROM teams WHERE id = $team_id");
            }
            $stmt_add_admin->close();
        } else {
            $error = "Error creating the team: " . $conn->error; // Show SQL error for debugging if needed
        }
        $stmt_insert_team->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Team | Smart Time Manager</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        .error {
            color: white;
            background-color: #d9534f; /* Red */
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }
        .success {
             color: white;
             background-color: #5cb85c; /* Green */
             padding: 10px;
             border-radius: 4px;
             margin-bottom: 15px;
             text-align: center;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }
         textarea {
            min-height: 80px; /* Give textarea some initial height */
            resize: vertical; /* Allow vertical resizing */
        }
        button[type="submit"] {
            background-color: #4A90E2;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            width: 100%;
        }
        button[type="submit"]:hover {
            background-color: #357abd;
        }
        .back-link {
            display: block;
            margin-top: 15px;
            text-align: center;
            color: #555;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Create New Team</h2>

        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <p><a href="my_teams.php" class="back-link">Go to My Teams</a></p>
            <a href="index.php" class="back-link">Back to Dashboard</a>
        <?php else: ?>
            <form method="post">
                <div>
                    <label for="team_name">Team Name:</label>
                    <input type="text" id="team_name" name="team_name" required value="<?php echo isset($_POST['team_name']) ? htmlspecialchars($_POST['team_name']) : ''; ?>">
                </div>
                <div>
                    <label for="team_description">Description (Optional):</label>
                    <textarea id="team_description" name="team_description"><?php echo isset($_POST['team_description']) ? htmlspecialchars($_POST['team_description']) : ''; ?></textarea>
                </div>
                <button type="submit">Create Team</button>
            </form>
            <a href="index.php" class="back-link">Back to Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>
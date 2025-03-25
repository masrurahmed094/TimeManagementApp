<?php
// --- login.php ---
session_start(); // Start session to track logged-in user

// Connect to the database
$conn = new mysqli("localhost", "root", "", "csc4200");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    // Prepare SQL to get user info
    $stmt = $conn->prepare("SELECT UserID, PasswordHash FROM user WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userID, $hash);
        $stmt->fetch();

        if (password_verify($password, $hash)) {
            // Password matches — set session
            $_SESSION["UserID"] = $userID;
            $_SESSION["Username"] = $username;

            echo "✅ Login successful! <a href='dashboard.php'>Go to dashboard</a>";
        } else {
            echo "❌ Incorrect password.";
        }
    } else {
        echo "❌ Username not found.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!-- Login Form -->
<h2>Login</h2>
<form method="POST" action="">
    Username: <input type="text" name="username" required><br><br>
    Password: <input type="password" name="password" required><br><br>
    <input type="submit" value="Login">
</form>
<a href="register.php">REGISTER</a>
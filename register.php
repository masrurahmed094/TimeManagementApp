<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    // Gather and sanitize inputs
    $name      = trim($_POST['name']);
    $email     = trim($_POST['email']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $question  = trim($_POST['security_question']);
    $answer    = trim($_POST['security_answer']);
    $answer_hash = password_hash($answer, PASSWORD_DEFAULT);

    // Check if the email already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $error = "This email address is already registered. Please log in.";
    } else {
        // Email not registered: insert new user with security question
        $stmt = $conn->prepare(
            "INSERT INTO users
             (name, email, password, security_question, security_answer)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssss", $name, $email, $password, $question, $answer_hash
        );

        if ($stmt->execute()) {
            header("Location: login.php");
            exit();
        } else {
            $error = "Error registering user: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="form-container">
    <h2>Register</h2>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="text" name="name" placeholder="Full Name" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>

        <label for="security_question">Security Question</label><br>
        <input type="text" id="security_question" name="security_question"
               placeholder="e.g. What was your first pet’s name?" required><br>

        <label for="security_answer">Answer</label><br>
        <input type="text" id="security_answer" name="security_answer"
               placeholder="Your answer" required><br>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login</a></p>
</div>
</body>
</html>

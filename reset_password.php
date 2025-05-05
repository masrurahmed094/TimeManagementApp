<?php
session_start();
include 'db.php';

// If they haven’t gone through the “forgot” form, bounce them back
if (empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_security_q'])) {
    header('Location: forgot_password.php');
    exit;
}

$error = '';
$uid   = $_SESSION['reset_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answer    = trim($_POST['answer']);
    $newpw     = trim($_POST['new_password']);

    // Fetch stored answer hash
    $stmt = $conn->prepare("SELECT security_answer FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($stored_hash);
    $stmt->fetch();
    $stmt->close();

    // Verify the answer
    if (!password_verify($answer, $stored_hash)) {
        $error = "Incorrect answer. Please try again.";
    } else {
        // Update to new password
        $new_hash = password_hash($newpw, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $new_hash, $uid);
        $upd->execute();
        $upd->close();

        // Clear session and redirect
        unset($_SESSION['reset_user_id'], $_SESSION['reset_security_q']);
        $_SESSION['login_message'] = "Password reset! You may now log in.";
        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Set New Password</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="form-container">
    <h2>Security Question</h2>
    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <p><strong><?= htmlspecialchars($_SESSION['reset_security_q']) ?></strong></p>
    <form method="post">
      <input type="text" name="answer" placeholder="Your answer" required><br>
      <input type="password" name="new_password" placeholder="New password" required><br>
      <button type="submit">Reset Password</button>
    </form>
    <p><a href="login.php">Back to Login</a></p>
  </div>
</body>
</html>

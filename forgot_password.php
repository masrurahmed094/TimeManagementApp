<?php
session_start();
include 'db.php';

$error   = '';
$stage   = 1;

// Stage 1: Ask for email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Submitted email, not yet answer
    if (isset($_POST['email']) && !isset($_POST['answer'])) {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email.";
        } else {
            // Fetch the user’s security question
            $stmt = $conn->prepare(
                "SELECT id, security_question
                 FROM users
                 WHERE email = ?"
            );
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($uid, $question);
            if ($stmt->fetch()) {
                $_SESSION['reset_user_id']     = $uid;
                $_SESSION['reset_security_q']  = $question;
                $stage = 2;
            } else {
                $error = "No account found for that email.";
            }
            $stmt->close();
        }

    // Stage 2: Verify answer + set new password
    } elseif (isset($_POST['answer'], $_POST['new_password'])) {
        if (empty($_SESSION['reset_user_id'])) {
            $error = "Session expired; please start again.";
            $stage = 1;
        } else {
            $uid    = $_SESSION['reset_user_id'];
            $answer = $_POST['answer'];
            $newpw  = $_POST['new_password'];

            // Get stored answer hash
            $stmt = $conn->prepare("SELECT security_answer FROM users WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->bind_result($ans_hash);
            $stmt->fetch();
            $stmt->close();

            if (!password_verify($answer, $ans_hash)) {
                $error = "Incorrect answer.";
                $stage = 2;
            } else {
                // Update to the new password
                $pw_hash = password_hash($newpw, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->bind_param("si", $pw_hash, $uid);
                $upd->execute();
                $upd->close();

                // Cleanup
                unset($_SESSION['reset_user_id'], $_SESSION['reset_security_q']);

                $_SESSION['login_message'] = "Password reset! You may now log in.";
                header("Location: login.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="form-container">
    <h2>Reset Password</h2>
    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($stage === 1): ?>
      <form method="post">
        <input type="email" name="email" placeholder="Your account email" required><br>
        <button type="submit">Continue</button>
      </form>
    <?php else: ?>
      <p><strong>Security question:</strong><br>
         <?= htmlspecialchars($_SESSION['reset_security_q']) ?></p>
      <form method="post">
        <input type="text" name="answer" placeholder="Your answer" required><br>
        <input type="password" name="new_password" placeholder="New password" required><br>
        <button type="submit">Set New Password</button>
      </form>
    <?php endif; ?>

    <p><a href="login.php">Back to Login</a></p>
  </div>
</body>
</html>

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Smart Time Manager</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="logo">Smart Time Manager</div>
        <nav>
            <ul>
                <li><a href="logout.php" class="logout-btn">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2>Welcome, <?php echo $_SESSION['user_name']; ?>!</h2>
        <p>Manage your tasks efficiently using AI-powered insights.</p>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Smart Time Manager. All rights reserved.</p>
    </footer>
</body>
</html>

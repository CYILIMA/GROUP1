<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo isset($page_title) ? $page_title : "UTAB Online"; ?>
    </title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="header">

    <div class="logo">
        <a href="index.php">UTAB <span>ONLINE</span></a>
    </div>

    <nav class="navbar" id="navbar">

        <a href="index.php">Home</a>
        <a href="cources.php">Courses</a>
        <a href="about.php">About Us</a>
        <a href="contact.php">Contact Us</a>

        <div class="auth-links">

            <?php if (isset($_SESSION['user_id'])): ?>

                <span class="welcome">
                    Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>

                <a href="logout.php" class="login-btn">Logout</a>

            <?php else: ?>

                <a href="login.php" class="login-btn">Sign In</a>
                <a href="signup.php" class="signup-btn">Sign Up</a>

            <?php endif; ?>

        </div>

    </nav>

    <button class="menu-btn" id="menuBtn">☰</button>

</header>
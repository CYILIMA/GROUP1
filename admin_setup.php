<?php
require "db.php";
require "admin_auth.php";

$admin_count = $conn->query("SELECT COUNT(*) AS total FROM admins")->fetch_assoc()["total"];
if ((int) $admin_count > 0) {
    header("Location: admin_login.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $admin_count = $conn->query("SELECT COUNT(*) AS total FROM admins")->fetch_assoc()["total"];
    if ((int) $admin_count > 0) {
        header("Location: admin_login.php");
        exit();
    }

    admin_verify_csrf();

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter your name and a valid email address.";
    } elseif (strlen($password) < 10) {
        $error = "Choose a password with at least 10 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "The passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashed_password);

        if ($stmt->execute()) {
            header("Location: admin_login.php?setup=complete");
            exit();
        }

        $error = "Admin setup could not be completed. Check that this email is not already in use.";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Administrator | UTAB Online</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="css/js/script.js?v=course-inline-images" defer></script>
</head>
<body class="admin-auth-page">
    <main class="admin-auth-shell">
        <a class="admin-brand" href="index.php">UTAB <span>ONLINE</span></a>
        <p class="admin-eyebrow">INITIAL SETUP</p>
        <h1>Create administrator</h1>
        <p class="admin-muted">This one-time form closes automatically after the first administrator is created.</p>

        <?php if ($error !== ""): ?>
            <div class="admin-alert admin-alert-error"><?php echo admin_escape($error); ?></div>
        <?php endif; ?>

        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
            <label for="name">Full name</label>
            <input id="name" name="name" type="text" maxlength="100" required>
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" maxlength="150" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" minlength="10" required>
            <label for="confirm_password">Confirm password</label>
            <input id="confirm_password" name="confirm_password" type="password" minlength="10" required>
            <button class="admin-button admin-button-primary" type="submit">Create administrator</button>
        </form>
        <a class="admin-back-link" href="index.php">Back to UTAB Online</a>
    </main>
</body>
</html>
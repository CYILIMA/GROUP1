<?php
require "db.php";
require "admin_auth.php";

if (!empty($_SESSION["admin_id"])) {
    header("Location: admin_dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    admin_verify_csrf();
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $stmt = $conn->prepare("SELECT id, name, password FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && password_verify($password, $admin["password"])) {
        session_regenerate_id(true);
        $_SESSION["admin_id"] = $admin["id"];
        $_SESSION["admin_name"] = $admin["name"];
        header("Location: admin_dashboard.php");
        exit();
    }

    $error = "Invalid email or password.";
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Sign In | UTAB Online</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="css/js/script.js?v=course-inline-images" defer></script>
</head>
<body class="admin-auth-page">
    <main class="admin-auth-shell">
        <a class="admin-brand" href="index.php">UTAB <span>ONLINE</span></a>
        <p class="admin-eyebrow">ADMINISTRATION</p>
        <h1>Sign in to your dashboard</h1>
        <?php if (isset($_GET["setup"])): ?>
            <div class="admin-alert admin-alert-success">Administrator created. Sign in to continue.</div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="admin-alert admin-alert-error"><?php echo admin_escape($error); ?></div>
        <?php endif; ?>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" autocomplete="username" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="admin-button admin-button-primary" type="submit">Sign in</button>
        </form>
        <a class="admin-back-link" href="index.php">Back to UTAB Online</a>
    </main>
</body>
</html>
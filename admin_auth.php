<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_require_login()
{
    if (empty($_SESSION["admin_id"])) {
        header("Location: admin_login.php");
        exit();
    }
}

function admin_csrf_token()
{
    if (empty($_SESSION["admin_csrf_token"])) {
        $_SESSION["admin_csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["admin_csrf_token"];
}

function admin_verify_csrf()
{
    $submitted_token = $_POST["csrf_token"] ?? "";
    $session_token = $_SESSION["admin_csrf_token"] ?? "";

    if (!$session_token || !hash_equals($session_token, $submitted_token)) {
        http_response_code(403);
        exit("Invalid form submission. Refresh the page and try again.");
    }
}

function admin_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
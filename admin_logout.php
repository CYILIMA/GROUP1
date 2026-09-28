<?php
require "admin_auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
	http_response_code(405);
	exit("Method not allowed.");
}

admin_verify_csrf();
unset($_SESSION["admin_id"], $_SESSION["admin_name"], $_SESSION["admin_csrf_token"]);
session_regenerate_id(true);
header("Location: admin_login.php");
exit();
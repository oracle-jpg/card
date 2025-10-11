<?php
// Tiyaking nakasama ang session bago i-destroy
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. I-unset ang lahat ng session variables
$_SESSION = array();

// 2. I-expire ang session cookie (para sa mas malinis na logout)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. I-destroy ang session
session_destroy();

// 4. I-redirect ang user pabalik sa login page (index.php)
header("Location: index.php");
exit;
?>
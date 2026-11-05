<?php
session_start();


$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();


setcookie("memberId", "", time() - 3600, "/");
setcookie("username", "", time() - 3600, "/");
setcookie("cus_name", "", time() - 3600, "/");
setcookie("phone", "", time() - 3600, "/");
setcookie("email", "", time() - 3600, "/");
setcookie("old_user", "", time() - 3600, "/");
setcookie("user", "", time() - 3600, "/");

header("Location: ../front_end/main.html");
exit();
?>

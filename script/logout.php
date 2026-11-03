<?php
setcookie("memberId", "", time() - 3600, "/");
setcookie("username", "", time() - 3600, "/");
setcookie("user", "", time() - 3600, "/");
setcookie("cus_name", "", time() - 3600, "/");
setcookie("phone", "", time() - 3600, "/");
setcookie("email", "", time() - 3600, "/");
header("Location: ../front_end/main.html");
exit();
?>
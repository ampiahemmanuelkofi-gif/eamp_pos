<?php
require_once '../../config/constants.php';
session_start();
session_unset();
session_destroy();
header("Location: " . BASE_URL . "/modules/auth/login.php");
exit();
?>
<?php
// logout.php
require_once 'config/db.php';
session_destroy();
setcookie(session_name(), '', time()-3600, '/');
header('Location: login.php?msg=logged_out');
exit;

<?php
// ============================================================
// logout.php  — Destroy session and remember-me token, redirect
// ============================================================
session_start();
require_once 'includes/db.php';

clearRememberToken();
session_destroy();
header('Location: index.php');
exit;

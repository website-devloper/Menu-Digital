<?php
require __DIR__ . '/../shared/config.php';
$_SESSION = [];
session_destroy();
header('Location: /admin/login.php');

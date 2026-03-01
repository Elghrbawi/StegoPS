<?php
require_once 'php/config/database.php';
require_once 'php/includes/auth.php';
$auth = new Auth();
$auth->logout();

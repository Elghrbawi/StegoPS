<?php
require_once __DIR__ . '/auth.php';
$adminAuth = new AdminAuth();
$adminAuth->logout();

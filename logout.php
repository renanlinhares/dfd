<?php
/**
 * Sistema DFD - Logout
 */
require_once 'includes/config.php';
logout();
header('Location: login.php');
exit;

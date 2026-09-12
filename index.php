<?php

define('BASE_URL', '');

require_once 'config/database.php';
require_once 'includes/auth.php';

header(
    'Location: ' . (
        isLoggedIn()
            ? BASE_URL . '/dashboard.php'
            : BASE_URL . '/login.php'
    )
);

exit();
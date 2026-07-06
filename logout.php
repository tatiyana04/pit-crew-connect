<?php
require_once __DIR__ . '/auth.php';

$loggedOutContext = current_session_context();

logout_user();

if ($loggedOutContext === 'staff') {
    header('Location: staff-login.php?logged_out=1');
} else {
    header('Location: index.php');
}

exit;
?>
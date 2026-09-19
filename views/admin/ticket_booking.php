<?php
// Redirect legacy ticket_booking.php to unified payment_recording.php
define('BASE_URL', '/ALL CODES/ADMS STMS/');
header('Location: ' . BASE_URL . 'views/admin/payment_recording.php');
exit();

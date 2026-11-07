<?php
define('NX_ADMIN_PUBLIC', true);
require __DIR__ . '/inc/boot.php';
logout();
redirect(admin_gate_on() ? '' : 'admin/login.php');   // with the hidden entrance on, the plain login address doesn't exist

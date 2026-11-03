<?php
define('NX_ADMIN_PUBLIC', true);
require __DIR__ . '/inc/boot.php';
logout();
redirect('admin/login.php');

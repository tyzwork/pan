<?php
/**
 * 后台退出登录
 */
require_once __DIR__ . '/common.php';
unset($_SESSION['admin_id']);
session_regenerate_id(true);
redirect('login.php');

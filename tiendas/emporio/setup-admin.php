<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
favor_session_start();
$user=favor_current_user();
if (!$user) { header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode(favor_base_path() . 'tiendas/emporio/admin.php'), true, 303); exit; }
header('Location: ' . favor_base_path() . 'tiendas/emporio/admin.php', true, 303);
exit;

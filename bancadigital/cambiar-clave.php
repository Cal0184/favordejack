<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/paths.php';
header('Location: ' . favor_base_path() . 'cuenta.php', true, 303);
exit;

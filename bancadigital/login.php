<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/paths.php';
$next = favor_base_path() . 'bancadigital/';
header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode($next), true, 303);
exit;

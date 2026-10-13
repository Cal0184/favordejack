<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/paths.php';
header('Location: ' . favor_base_path() . 'logout.php?next=' . rawurlencode(favor_base_path() . 'bancadigital/'), true, 303);
exit;

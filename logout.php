<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$next = favor_safe_next($_GET['next'] ?? '/favordejack/');
favor_logout();
header('Location: ' . $next, true, 303);
exit;

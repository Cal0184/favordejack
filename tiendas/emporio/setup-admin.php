<?php
declare(strict_types=1);
// Página antigua: la administración usa ahora la sesión de toda la web.
header('Location: admin.php', true, 303);
exit;

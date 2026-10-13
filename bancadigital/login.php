<?php
declare(strict_types=1);
$next = '/favordejack/bancadigital/';
header('Location: /favordejack/login.php?next=' . rawurlencode($next), true, 303);
exit;

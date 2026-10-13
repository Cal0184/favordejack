<?php
declare(strict_types=1);
header('Location: /favordejack/logout.php?next=' . rawurlencode('/favordejack/bancadigital/'), true, 303);
exit;

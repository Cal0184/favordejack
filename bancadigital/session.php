<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
favor_session_start();

// Compatibilidad temporal con código antiguo de la Banca.
$__favorUser = favor_current_user();
if ($__favorUser) {
    $_SESSION['banca_user'] = [
        'id' => (int)$__favorUser['id'],
        'username' => (string)$__favorUser['username'],
        'playerId' => (string)$__favorUser['playerId'],
        'role' => (string)$__favorUser['role'],
        'active' => true,
    ];
} else {
    unset($_SESSION['banca_user']);
}
$_SESSION['csrf'] = favor_csrf();

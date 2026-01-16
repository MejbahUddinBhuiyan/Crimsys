<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start(['cookie_httponly'=>true, 'cookie_samesite'=>'Lax']);
}
$_SESSION = [];
session_destroy();
$go = isset($_GET['go']) ? (string)$_GET['go'] : '/Crimsys/index.php';
header('Location: '.$go);
exit;

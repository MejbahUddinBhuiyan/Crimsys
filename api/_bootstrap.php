<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start(['cookie_httponly'=>true, 'cookie_samesite'=>'Lax']);
}
header_remove('X-Powered-By');
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
date_default_timezone_set('Asia/Dhaka');
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/dbconnect.php';  // defines $conn (mysqli)
if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_not_connected']);
  exit;
}
$conn->set_charset('utf8mb4');

function json_ok(array $data=[], int $code=200): void {
  if (!headers_sent()) http_response_code($code);
  echo json_encode(['ok'=>true]+$data, JSON_UNESCAPED_UNICODE);
  exit;
}
function json_fail(string $msg='error', int $code=400, array $ex=[]): void {
  if (!headers_sent()) http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg]+$ex, JSON_UNESCAPED_UNICODE);
  exit;
}
function read_json(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '[]', true);
  if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    json_fail('invalid_json', 400, ['detail'=>json_last_error_msg()]);
  }
  return $data;
}
function require_admin(): void {
  if (empty($_SESSION['admin']) || $_SESSION['admin']!==true) {
    json_fail('unauthorized', 401);
  }
}

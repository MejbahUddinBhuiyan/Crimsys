<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../dbconnect.php';

function json_ok(array $d=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);
$copid = preg_replace('/\D/','', (string)($in['copid'] ?? ''));
if (strlen($copid)!==8) json_fail('invalid_copid', 422);

// double-check exists
$chk = $conn->prepare("SELECT CopID FROM Cop WHERE CopID=? LIMIT 1");
$chk->bind_param('i', $copid);
$chk->execute();
$r = $chk->get_result();
$exists = $r && $r->num_rows>0;
$chk->close();
if(!$exists) json_fail('not_found', 404);

// delete
$del = $conn->prepare("DELETE FROM Cop WHERE CopID=? LIMIT 1");
$del->bind_param('i', $copid);
$del->execute();
$affected = $del->affected_rows;
$del->close();

if ($affected < 1) json_fail('delete_failed', 500);
json_ok();

<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';

function ok($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function bad($m,$c=400){ http_response_code($c); ok(['ok'=>false,'error'=>$m]); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('method_not_allowed',405);

$raw = file_get_contents('php://input');
$jo  = $raw ? json_decode($raw,true) : null;

$firId = isset($_POST['firId']) ? (int)$_POST['firId'] : (int)($jo['firId'] ?? 0);
$text  = $_POST['text'] ?? ($jo['text'] ?? '');
$byCop = isset($_POST['byCopId']) ? (int)$_POST['byCopId'] : (int)($jo['byCopId'] ?? 0);

if ($firId<=0) bad('missing_firId');
$text = trim($text);
if ($text==='') bad('empty_note');

$st = $conn->prepare("INSERT INTO fir_notes (FirID, NoteText, ByCopID) VALUES (?, ?, ?)");
$st->bind_param('isi',$firId,$text,$byCop);
$st->execute();
$noteId = $st->insert_id;
$st->close();

// timeline
$msg = "Note added";
$tl  = $conn->prepare("INSERT INTO firtimeline (FirID, Type, Message, ByCopID) VALUES (?, 'Note', ?, ?)");
$tl->bind_param('isi',$firId,$msg,$byCop);
$tl->execute();
$tl->close();

ok(['ok'=>true,'noteId'=>$noteId]);

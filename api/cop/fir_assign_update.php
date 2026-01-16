<?php
// C:\xmp\htdocs\Crimsys\api\cop\fir_assign_update.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';



try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('method_not_allowed');
    $firId    = isset($_POST['firId']) ? (int)$_POST['firId'] : 0;
    $byCopId  = isset($_POST['byCopId']) ? (int)$_POST['byCopId'] : 0;
    $newCopId = isset($_POST['newCopId']) ? (int)$_POST['newCopId'] : 0;
    $reason   = trim($_POST['reason'] ?? '');

    if ($firId <= 0 || $byCopId <= 0 || $newCopId <= 0) throw new Exception('missing_fields');

    $pdo->beginTransaction();

    $cur = $pdo->prepare("SELECT AssignedCopID FROM fir WHERE FirID = ? FOR UPDATE");
    $cur->execute([$firId]);
    $row = $cur->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('not_found');
    $oldCopId = (int)$row['AssignedCopID'];

    $upd = $pdo->prepare("UPDATE fir SET AssignedCopID = ?, UpdatedAt = NOW() WHERE FirID = ?");
    $upd->execute([$newCopId, $firId]);

    $hst = $pdo->prepare("
      INSERT INTO fir_assignment_history (FirID, OldCopID, NewCopID, Reason, ByCopID)
      VALUES (?, ?, ?, ?, ?)
    ");
    $hst->execute([$firId, $oldCopId, $newCopId, $reason, $byCopId]);

    $tl = $pdo->prepare("
      INSERT INTO firtimeline (FirID, Type, Message, ByCopID)
      VALUES (?, 'Assignment', ?, ?)
    ");
    $msg = "Reassigned from Cop #{$oldCopId} to Cop #{$newCopId}" . ($reason ? " ({$reason})" : '');
    $tl->execute([$firId, $msg, $byCopId]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'oldCopId' => $oldCopId, 'newCopId' => $newCopId]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

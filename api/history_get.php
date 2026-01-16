<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../dbconnect.php';

    if (!isset($_GET['historyId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing historyId']);
        exit;
    }

    $historyId = (int)$_GET['historyId'];

    $sql = "
        SELECT
            h.HistoryID,
            h.DateTime,
            h.CaseStatus,
            h.Location,
            h.CrimeTypeID,
            ct.Name AS CrimeTypeName,
            h.OfficerInCharge,
            cop.Name AS OfficerName,
            h.CriminalID
        FROM criminalhistory h
        LEFT JOIN crimetype ct ON h.CrimeTypeID = ct.CrimeTypeID
        LEFT JOIN cop       ON h.OfficerInCharge = cop.CopID
        WHERE h.HistoryID = :historyId
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':historyId', $historyId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $row ?: null], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

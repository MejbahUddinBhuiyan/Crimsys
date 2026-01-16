<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../dbconnect.php';

    // Accept JSON body or form-POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) { $input = $_POST; }

    $historyId   = isset($input['historyId'])   ? (int)$input['historyId'] : null;
    $caseStatus  = isset($input['caseStatus'])  ? trim($input['caseStatus']) : null;
    $officerId   = array_key_exists('officerId', $input)   ? ($input['officerId'] === '' ? null : (int)$input['officerId']) : null;
    $crimeTypeId = array_key_exists('crimeTypeId', $input) ? ($input['crimeTypeId'] === '' ? null : (int)$input['crimeTypeId']) : null;
    $location    = isset($input['location'])    ? trim($input['location']) : null;

    if (empty($historyId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'historyId is required']);
        exit;
    }

    // Build dynamic SET list (only update provided fields)
    $set = [];
    $params = [':historyId' => $historyId];

    if ($caseStatus !== null && $caseStatus !== '') {
        $set[] = "CaseStatus = :caseStatus";
        $params[':caseStatus'] = $caseStatus;
    }
    if (array_key_exists('officerId', $input)) {
        $set[] = "OfficerInCharge = :officerId";
        // allow NULL
        $params[':officerId'] = $officerId !== null ? $officerId : null;
    }
    if (array_key_exists('crimeTypeId', $input)) {
        $set[] = "CrimeTypeID = :crimeTypeId";
        $params[':crimeTypeId'] = $crimeTypeId !== null ? $crimeTypeId : null;
    }
    if ($location !== null) {
        $set[] = "Location = :location";
        $params[':location'] = $location;
    }

    if (empty($set)) {
        echo json_encode(['success' => true, 'updated' => 0, 'message' => 'Nothing to update']);
        exit;
    }

    $sql = "UPDATE criminalhistory SET " . implode(', ', $set) . " WHERE HistoryID = :historyId";
    $stmt = $pdo->prepare($sql);

    // Bind with correct types
    foreach ($params as $k => $v) {
        if ($k === ':historyId' || $k === ':officerId' || $k === ':crimeTypeId') {
            if ($v === null) { $stmt->bindValue($k, null, PDO::PARAM_NULL); }
            else { $stmt->bindValue($k, (int)$v, PDO::PARAM_INT); }
        } else {
            $stmt->bindValue($k, $v);
        }
    }

    $stmt->execute();
    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

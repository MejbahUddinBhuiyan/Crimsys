<?php
// /api/history_list.php
header('Content-Type: application/json');

try {
    // Uses your existing dbconnect.php which creates $pdo (PDO)
    require_once __DIR__ . '/../dbconnect.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('DB connection ($pdo) not available from dbconnect.php');
    }

    // -------- inputs --------
    $name       = isset($_GET['name'])       ? trim($_GET['name'])       : null;
    $nid        = isset($_GET['nid'])        ? trim($_GET['nid'])        : null;
    $criminalId = isset($_GET['criminalId']) ? (int) $_GET['criminalId'] : null;
    $status     = isset($_GET['status'])     ? trim($_GET['status'])     : null; // Open | Under Trial | Solved | All/empty
    $limit      = isset($_GET['limit'])      ? (int) $_GET['limit']      : 25;
    $offset     = isset($_GET['offset'])     ? (int) $_GET['offset']     : 0;

    // safety on paging
    if ($limit <= 0)  $limit  = 25;
    if ($limit > 500) $limit  = 500;
    if ($offset < 0)  $offset = 0;

    // -------- base query --------
    $sql = "
        SELECT
            -- identity (requested)
            c.CriminalID,
            c.FullName,

            -- history
            h.HistoryID,
            h.DateTime,
            h.CaseStatus,
            h.Location,
            h.CrimeTypeID,

            -- lookups
            ct.Name AS CrimeTypeName,
            h.OfficerInCharge,
            cop.Name AS OfficerName
        FROM criminalhistory h
        LEFT JOIN criminal  c  ON h.CriminalID     = c.CriminalID
        LEFT JOIN crimetype ct ON h.CrimeTypeID    = ct.CrimeTypeID
        LEFT JOIN cop       ON h.OfficerInCharge   = cop.CopID
        WHERE 1 = 1
    ";

    $params = [];

    // filters
    if (!empty($criminalId)) {
        $sql .= " AND h.CriminalID = :criminalId";
        $params[':criminalId'] = $criminalId;
    }
    if (!empty($name)) {
        $sql .= " AND c.FullName LIKE :name";
        $params[':name'] = "%{$name}%";
    }
    if (!empty($nid)) {
        $sql .= " AND c.NID = :nid";
        $params[':nid'] = $nid;
    }
    if (!empty($status) && strcasecmp($status, 'All') !== 0) {
        $sql .= " AND h.CaseStatus = :status";
        $params[':status'] = $status;
    }

    // order & paging
    $sql .= " ORDER BY h.DateTime DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    // bind dynamic params
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    // bind paging as integers
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

<?php
// /Crimsys/api/cop/fir_create.php
header('Content-Type: application/json');
session_start();

// ---- DB (match your credentials or use your inc/db.php) ----
$dsn = 'mysql:host=localhost;dbname=crimsys;charset=utf8mb4';
$user = 'root';
$pass = '';
$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ];
try { $pdo = new PDO($dsn, $user, $pass, $options); }
catch (Exception $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_connect_failed']); exit; }

// ---- read JSON body ----
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) { echo json_encode(['ok'=>false,'error'=>'invalid_json']); exit; }

// ---- inputs ----
$location       = trim($body['location'] ?? '');
$description    = trim($body['description'] ?? '');
$suspectedInfo  = trim($body['suspectedInfo'] ?? '');
$status         = trim($body['status'] ?? 'Open');
$crimeTypeId    = isset($body['crimeTypeId']) && $body['crimeTypeId'] !== '' ? (int)$body['crimeTypeId'] : null;
$persons        = is_array($body['persons'] ?? null) ? $body['persons'] : [];
$suspects       = is_array($body['suspects'] ?? null) ? $body['suspects'] : [];

// parse datetime-local -> mysql datetime
$incidentDateIn = trim($body['incidentDate'] ?? '');
$incidentDate   = null;
if ($incidentDateIn !== '') {
  // Accept "Y-m-d H:i[:s]" or "Y-m-d\TH:i[:s]"
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $incidentDateIn)
     ?: DateTime::createFromFormat('Y-m-d H:i',    $incidentDateIn)
     ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $incidentDateIn)
     ?: DateTime::createFromFormat('Y-m-d\TH:i',   $incidentDateIn);
  if ($dt) $incidentDate = $dt->format('Y-m-d H:i:s');
}
if (!$incidentDate) { echo json_encode(['ok'=>false,'error'=>'invalid_incident_date']); exit; }

// who is filing
$copId = isset($_SESSION['CopID']) ? (int)$_SESSION['CopID'] : 10000003; // fallback while session wiring
$assignedCopId = isset($body['assignedCopId']) && $body['assignedCopId'] ? (int)$body['assignedCopId'] : $copId;

// ---- basic validation ----
if ($location === '' || $description === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_required_fields']); exit;
}

try {
  $pdo->beginTransaction();

  // Insert FIR (includes CrimeTypeID)
  $sql = "INSERT INTO fir
            (Location, SuspectedInfo, Status, VictimInfo, Description, IncidentDate, CrimeTypeID, CopID, AssignedCopID)
          VALUES
            (:loc, :sus, :status, NULL, :desc, :dt, :ctype, :cop, :assigned)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':loc'      => $location,
    ':sus'      => $suspectedInfo !== '' ? $suspectedInfo : NULL,
    ':status'   => $status !== '' ? $status : 'Open',
    ':desc'     => $description,
    ':dt'       => $incidentDate,
    ':ctype'    => $crimeTypeId,
    ':cop'      => $copId,
    ':assigned' => $assignedCopId
  ]);
  $firId = (int)$pdo->lastInsertId();

  // firdetails rows (optional)
  if (!empty($persons)) {
    $stmtP = $pdo->prepare("INSERT INTO firdetails (Role, PersonName, ContactInfo, FirID) VALUES (:role,:name,:contact,:fir)");
    foreach ($persons as $p) {
      $stmtP->execute([
        ':role'    => ($p['role'] ?? 'Victim'),
        ':name'    => trim($p['name'] ?? ''),
        ':contact' => trim($p['contact'] ?? ''),
        ':fir'     => $firId
      ]);
    }
  }
  if (!empty($suspects)) {
    $stmtS = $pdo->prepare("INSERT INTO firdetails (Role, PersonName, ContactInfo, FirID) VALUES (:role,:name,:contact,:fir)");
    foreach ($suspects as $s) {
      $stmtS->execute([
        ':role'    => ($s['role'] ?? 'Suspect'),
        ':name'    => trim($s['name'] ?? 'Unknown'),
        ':contact' => trim($s['contact'] ?? ''),
        ':fir'     => $firId
      ]);
    }
  }

  $pdo->commit();
  echo json_encode(['ok'=>true, 'FirID'=>$firId]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'insert_failed', 'detail'=>$e->getMessage()]);
}

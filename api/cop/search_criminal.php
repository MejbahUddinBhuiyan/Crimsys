<?php
// /Crimsys/api/cop/search_criminal.php
header('Content-Type: application/json; charset=utf-8');

try {
  /* ---------- DB ---------- */
  $pdo = new PDO(
    'mysql:host=localhost;dbname=crimsys;charset=utf8mb4',
    'root', '', [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
  );

  /* ---------- Input (POST: JSON, x-www-form-urlencoded; GET for easy testing) ---------- */
  $raw = file_get_contents('php://input');
  $json = json_decode($raw, true);
  $q     = '';
  $role  = '';
  $limit = 20;
  $page  = 1;

  if (is_array($json)) {                     // JSON body
    $q    = isset($json['q'])    ? trim($json['q'])    : '';
    $role = isset($json['role']) ? trim($json['role']) : '';
    $limit= isset($json['limit'])? (int)$json['limit']  : 20;
    $page = isset($json['page']) ? (int)$json['page']   : 1;
  } else {                                   // form or GET
    $q    = isset($_REQUEST['q'])    ? trim($_REQUEST['q'])    : '';
    $role = isset($_REQUEST['role']) ? trim($_REQUEST['role']) : '';
    $limit= isset($_REQUEST['limit'])? (int)$_REQUEST['limit']  : 20;
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page']   : 1;
  }

  if ($limit <= 0 || $limit > 200) $limit = 20;
  if ($page  <= 0) $page = 1;
  $offset = ($page - 1) * $limit;

  // build patterns
  $likeQ  = '%' . preg_replace('/\s+/', ' ', $q) . '%';
  $nidExact = preg_match('/^\d+$/', $q) ? $q : ''; // treat pure digits as exact NID too

  /* ---------- SQL ---------- */
  // LEFT JOIN keeps criminals that are not yet in involves.
  // Role filter is collapsible with (:role='' OR i.RoleInCase=:role)
  $sql = "
    SELECT
      c.CriminalID,
      c.FullName,
      c.NID,
      c.Photo,
      c.Zip, c.Street, c.City,
      COUNT(DISTINCT i.FirID) AS FirCount
    FROM criminal c
    LEFT JOIN involves i
      ON i.CriminalID = c.CriminalID
      AND (:role = '' OR i.RoleInCase = :role)   -- apply role inside join
    WHERE
      (:nidExact <> '' AND c.NID = :nidExact)
      OR c.NID      LIKE :likeQ
      OR c.FullName LIKE :likeQ
    GROUP BY c.CriminalID
    ORDER BY c.CreatedAt DESC, c.CriminalID DESC
    LIMIT :limit OFFSET :offset
  ";

  $st = $pdo->prepare($sql);
  $st->bindValue(':role', $role, PDO::PARAM_STR);
  $st->bindValue(':nidExact', $nidExact, PDO::PARAM_STR);
  $st->bindValue(':likeQ', $likeQ, PDO::PARAM_STR);
  $st->bindValue(':limit', $limit, PDO::PARAM_INT);
  $st->bindValue(':offset', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // normalize photo
  $items = [];
  foreach ($rows as $r) {
    $photo = $r['Photo'];
    if (!$photo) {
      $photo = '/img/avatars/placeholder.png'; // your placeholder
    } else if ($photo[0] === '/') {
      // path is OK (e.g., /img/criminals/XXX.png)
    } else {
      // stored without leading slash
      $photo = '/' . ltrim($photo, '/');
    }

    $items[] = [
      'criminalId' => (int)$r['CriminalID'],
      'fullName'   => $r['FullName'],
      'nid'        => $r['NID'],
      'photo'      => $photo,
      'zip'        => $r['Zip'],
      'street'     => $r['Street'],
      'city'       => $r['City'],
      'firCount'   => (int)$r['FirCount'],
    ];
  }

  echo json_encode(['ok' => true, 'items' => $items, 'count' => count($items)]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

<?php
require __DIR__ . '/_util.php';

$types=""; $params=[];
$filters = build_filters($types, $params);
$filters = $filters ? "$filters AND f.Priority='High'" : "WHERE f.Priority='High'";

$sql = "
  SELECT cz.District, cz.Thana, m.ZoneID, COUNT(*) AS HighPriorityCases
  FROM fir f
  JOIN fir_zone_map m ON m.FirID = f.FirID
  JOIN crimezone cz   ON cz.ZoneID = m.ZoneID
  $filters
  GROUP BY m.ZoneID
  ORDER BY HighPriorityCases DESC
";
$data = run_query($mysqli, $sql, $types, $params);

echo json_encode(["ok"=>true, "data"=>$data]);

<?php
require __DIR__ . '/_util.php';

$types=""; $params=[];
$filters = build_filters($types, $params);

$sql = "
  SELECT DATE_FORMAT(f.IncidentDate, '%Y-%m') AS Month, cz.District, cz.Thana,
         COUNT(*) AS FIR_Count
  FROM fir f
  JOIN fir_zone_map m ON m.FirID = f.FirID
  JOIN crimezone cz   ON cz.ZoneID = m.ZoneID
  $filters
  GROUP BY Month, m.ZoneID
  ORDER BY Month ASC, FIR_Count DESC
";
$data = run_query($mysqli, $sql, $types, $params);

echo json_encode(["ok"=>true, "data"=>$data]);

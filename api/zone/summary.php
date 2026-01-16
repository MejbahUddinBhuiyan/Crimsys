<?php
require __DIR__ . '/_util.php';

$types = ""; $params = [];
$filters = build_filters($types, $params);

$sql = "
  SELECT cz.District, cz.Thana, m.ZoneID, COUNT(*) AS TotalFIRs
  FROM fir f
  JOIN fir_zone_map m ON m.FirID = f.FirID
  JOIN crimezone cz   ON cz.ZoneID = m.ZoneID
  $filters
  GROUP BY m.ZoneID
  ORDER BY TotalFIRs DESC, cz.District, cz.Thana
";
$data = run_query($mysqli, $sql, $types, $params);

echo json_encode(["ok"=>true, "data"=>$data]);

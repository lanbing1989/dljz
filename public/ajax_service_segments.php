<?php
require 'auth.php';
require 'db.php';
$service_period_id = intval($_GET['service_period_id'] ?? 0);
$options = '<option value="">不选择分段</option>';
if ($service_period_id > 0) {
    $stmt = $db->prepare("SELECT * FROM service_segments WHERE service_period_id=:service_period_id ORDER BY id ASC");
    $stmt->bindValue(':service_period_id', $service_period_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $txt = $row['start_date'].' ~ '.$row['end_date'].' / '.$row['package_type'].' / '.$row['price_per_year'].'元/年';
        $options .= '<option value="'.$row['id'].'">'.htmlspecialchars($txt).'</option>';
    }
}
echo $options;
?>
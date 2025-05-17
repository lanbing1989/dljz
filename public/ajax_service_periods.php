<?php
require 'db.php';
$contract_id = intval($_GET['contract_id'] ?? 0);
$options = '<option value="">请选择服务期</option>';
if ($contract_id > 0) {
    $stmt = $db->prepare("SELECT * FROM service_periods WHERE contract_id=:contract_id ORDER BY id DESC");
    $stmt->bindValue(':contract_id', $contract_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $txt = $row['service_start'].' ~ '.$row['service_end'].' / '.$row['package_type'].' / '.$row['month_count'].'月';
        $options .= '<option value="'.$row['id'].'">'.htmlspecialchars($txt).'</option>';
    }
}
echo $options;
?>
<?php
require 'db.php';
$client_id = intval($_GET['client_id'] ?? 0);

$stmt = $db->prepare("SELECT service_start, service_end, package_type, price_per_year, segment_fee FROM contracts WHERE id=:id");
$stmt->bindValue(':id', $client_id, SQLITE3_INTEGER);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if ($row) {
    echo json_encode(['ok'=>true, 'data'=>$row]);
} else {
    echo json_encode(['ok'=>false, 'msg'=>'未找到客户信息']);
}
?>
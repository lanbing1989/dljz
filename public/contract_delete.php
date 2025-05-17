<?php
require 'auth.php';
require 'db.php';
$id = intval($_GET['id']);
// 删除 payments
$stmt = $db->prepare("DELETE FROM payments WHERE contract_id=:id");
$stmt->bindValue(':id', $id, SQLITE3_INTEGER); $stmt->execute();
// 删除 service_segments
$stmt2 = $db->prepare("SELECT id FROM service_periods WHERE contract_id=:id");
$stmt2->bindValue(':id', $id, SQLITE3_INTEGER);
$periods = $stmt2->execute();
while($p = $periods->fetchArray(SQLITE3_ASSOC)) {
    $pid = $p['id'];
    $stmt3 = $db->prepare("DELETE FROM service_segments WHERE service_period_id=:pid");
    $stmt3->bindValue(':pid', $pid, SQLITE3_INTEGER);
    $stmt3->execute();
}
// 删除 service_periods
$stmt4 = $db->prepare("DELETE FROM service_periods WHERE contract_id=:id");
$stmt4->bindValue(':id', $id, SQLITE3_INTEGER); $stmt4->execute();
// 删除 contract
$stmt5 = $db->prepare("DELETE FROM contracts WHERE id=:id");
$stmt5->bindValue(':id', $id, SQLITE3_INTEGER); $stmt5->execute();
header('Location: index.php');
exit;
?>
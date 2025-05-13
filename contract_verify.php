<?php
require 'db.php';
$no = $_GET['no'] ?? '';
if (!$no) die('参数错误');
$stmt = $db->prepare("SELECT contract_no,contract_hash,sign_date,sign_phone,sign_ip FROM contracts_agreement WHERE contract_no=:no");
$stmt->bindValue(':no', $no, SQLITE3_TEXT);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
if (!$row) die('未查到该合同');
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>合同查验</title></head>
<body>
<h3>合同查验</h3>
<table>
<tr><td>合同编号</td><td><?=htmlspecialchars($row['contract_no'])?></td></tr>
<tr><td>合同哈希</td><td><?=htmlspecialchars($row['contract_hash'])?></td></tr>
<tr><td>签署日期</td><td><?=htmlspecialchars($row['sign_date'])?></td></tr>
<tr><td>签署手机号</td><td><?=htmlspecialchars($row['sign_phone'])?></td></tr>
<tr><td>签署IP</td><td><?=htmlspecialchars($row['sign_ip'])?></td></tr>
</table>
</body>
</html>
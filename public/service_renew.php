<?php
require 'auth.php';
require 'db.php';
$contract_id = intval($_GET['contract_id']);

$stmt = $db->prepare("SELECT * FROM contracts WHERE id=:id");
$stmt->bindValue(':id', $contract_id, SQLITE3_INTEGER);
$contract = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

$stmt2 = $db->prepare("SELECT * FROM service_periods WHERE contract_id=:id ORDER BY service_end DESC LIMIT 1");
$stmt2->bindValue(':id', $contract_id, SQLITE3_INTEGER);
$last = $stmt2->execute()->fetchArray(SQLITE3_ASSOC);

if ($_SERVER['REQUEST_METHOD']=='POST') {
    $start = date('Y-m-d', strtotime($last['service_end'] . ' +1 day'));
    $end = date('Y-m-d', strtotime($start . ' +1 year -1 day'));
    $stmt = $db->prepare("INSERT INTO service_periods (contract_id, service_start, service_end, total_fee) VALUES (:contract_id, :service_start, :service_end, :total_fee)");
    $stmt->bindValue(':contract_id', $contract_id, SQLITE3_INTEGER);
    $stmt->bindValue(':service_start', $start, SQLITE3_TEXT);
    $stmt->bindValue(':service_end', $end, SQLITE3_TEXT);
    $stmt->bindValue(':total_fee', $_POST['total_fee'], SQLITE3_TEXT);
    $stmt->execute();
    header('Location: contract_detail.php?id='.urlencode($contract_id));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>续费</title>
    <link href="/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <script src="/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
<?php include('navbar.php'); ?>
<div class="container mt-4">
    <h2 class="mb-4">续费（新服务期）</h2>
    <div class="mb-3">
        <strong>客户：</strong><?=htmlspecialchars($contract['client_name'])?>
    </div>
    <div class="mb-3">
        <strong>上一个服务期：</strong>
        <?=date('Y.m.d',strtotime($last['service_start']))?> - <?=date('Y.m.d',strtotime($last['service_end']))?>
    </div>
    <form method="post" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">新服务期起止日期</label>
            <input type="text" class="form-control" value="<?=date('Y.m.d',strtotime($last['service_end'].' +1 day'))?> - <?=date('Y.m.d',strtotime($last['service_end'].' +1 year'))?>" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">新服务期总金额</label>
            <input type="number" step="0.01" class="form-control" name="total_fee" required>
        </div>
        <button type="submit" class="btn btn-success">续费</button>
        <a href="contract_detail.php?id=<?=urlencode($contract_id)?>" class="btn btn-secondary">返回</a>
    </form>
</div>
</body>
</html>
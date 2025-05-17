<?php
require 'auth.php';
require 'db.php';
$period_id = intval($_GET['period_id']);

// 查询服务期和所属合同（参数绑定）
$stmt_period = $db->prepare("SELECT * FROM service_periods WHERE id=:id");
$stmt_period->bindValue(':id', $period_id, SQLITE3_INTEGER);
$period = $stmt_period->execute()->fetchArray(SQLITE3_ASSOC);

$stmt_contract = $db->prepare("SELECT * FROM contracts WHERE id=:id");
$stmt_contract->bindValue(':id', intval($period['contract_id']), SQLITE3_INTEGER);
$contract = $stmt_contract->execute()->fetchArray(SQLITE3_ASSOC);

// 查询所有分段
$segments = [];
$stmt_seg = $db->prepare("SELECT * FROM service_segments WHERE service_period_id=:period_id");
$stmt_seg->bindValue(':period_id', $period_id, SQLITE3_INTEGER);
$res = $stmt_seg->execute();
while($row = $res->fetchArray(SQLITE3_ASSOC)) $segments[] = $row;

// 查询合同金额（本服务期所有分段金额之和）
$stmt_amount = $db->prepare("SELECT IFNULL(SUM(segment_fee),0) as total FROM service_segments WHERE service_period_id=:period_id");
$stmt_amount->bindValue(':period_id', $period_id, SQLITE3_INTEGER);
$contract_amount = $stmt_amount->execute()->fetchArray(SQLITE3_ASSOC)['total'];

// 查询当前已收费总额
$stmt_paid = $db->prepare("
    SELECT IFNULL(SUM(amount),0) as paid
    FROM payments
    WHERE is_temp=0 AND service_segment_id IN (
        SELECT id FROM service_segments WHERE service_period_id=:period_id
    )
");
$stmt_paid->bindValue(':period_id', $period_id, SQLITE3_INTEGER);
$paid_amount = $stmt_paid->execute()->fetchArray(SQLITE3_ASSOC)['paid'];

$error = '';
if ($_SERVER['REQUEST_METHOD']=='POST') {
    $segment_id = intval($_POST['segment_id']);
    $pay_date = $_POST['pay_date'];
    $amount = floatval($_POST['amount']);
    $remark = $_POST['remark'];

    // 校验收费不能超合同金额
    if ($paid_amount + $amount > $contract_amount + 0.001) {  // 加0.001防止浮点误差
        $error = '本服务期总收费金额不能超过合同金额（'.number_format($contract_amount,2).'元）！';
    } else {
        $stmt = $db->prepare("INSERT INTO payments (contract_id, service_segment_id, pay_date, amount, remark, is_temp) VALUES (:contract_id, :service_segment_id, :pay_date, :amount, :remark, 0)");
        $stmt->bindValue(':contract_id', $period['contract_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':service_segment_id', $segment_id, SQLITE3_INTEGER);
        $stmt->bindValue(':pay_date', $pay_date);
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':remark', $remark);
        $stmt->execute();
        header('Location: payment_list.php?period_id='.$period_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>新增收费</title>
    <link href="/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <script src="/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
<?php include('navbar.php'); ?>
<div class="container mt-4">
    <h4>客户：<?=htmlspecialchars($contract['client_name'])?></h4>
    <div class="mb-2">
        <b>服务期：</b><?=date('Y.m.d',strtotime($period['service_start']))?> - <?=date('Y.m.d',strtotime($period['service_end']))?>
    </div>
    <div class="mb-2">
        <b>服务期合同金额：</b><?=number_format($contract_amount,2)?> 元，
        <b>已收：</b><?=number_format($paid_amount,2)?> 元
    </div>
    <?php if($error): ?>
        <div class="alert alert-danger"><?=$error?></div>
    <?php endif; ?>
    <form method="post" class="bg-white p-4 rounded shadow-sm mb-3">
        <div class="mb-3">
            <label class="form-label">分段期间</label>
            <select name="segment_id" class="form-control" required>
                <?php foreach($segments as $s): ?>
                    <option value="<?=$s['id']?>"><?=date('Y.m.d',strtotime($s['start_date']))?> - <?=date('Y.m.d',strtotime($s['end_date']))?>（年费<?=$s['price_per_year']?>，套餐<?=$s['package_type']?>）
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">收费日期</label>
            <input type="date" class="form-control" name="pay_date" value="<?=date('Y-m-d')?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">金额</label>
            <input type="number" class="form-control" name="amount" step="0.01" required>
        </div>
        <div class="mb-3">
            <label class="form-label">备注</label>
            <input type="text" class="form-control" name="remark">
        </div>
        <button type="submit" class="btn btn-success">保存</button>
        <a href="payment_list.php?period_id=<?=$period_id?>" class="btn btn-secondary">返回</a>
    </form>
</div>
</body>
</html>
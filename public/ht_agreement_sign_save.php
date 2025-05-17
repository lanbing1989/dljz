<?php
session_start();
require 'db.php';

// 用uuid参数，不用id
$uuid = $_GET['uuid'] ?? '';

// 查询合同及关键信息
$stmt = $db->prepare("
SELECT a.*, t.content AS template_content, c.client_name, c.contact_person, c.contact_phone, c.contact_email, c.remark
FROM contracts_agreement a
LEFT JOIN contract_templates t ON a.template_id = t.id
LEFT JOIN contracts c ON a.client_id = c.id
WHERE a.uuid = :uuid
");
$stmt->bindValue(':uuid', $uuid, SQLITE3_TEXT);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$row) {
    echo json_encode(['ok'=>false, 'msg'=>'合同不存在']);
    exit;
}
if (!empty($row['sign_image']) && file_exists(__DIR__ . '/' . $row['sign_image'])) {
    echo json_encode(['ok'=>false, 'msg'=>'该合同已签署，不能重复签署！']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$imgBase64 = $data['signature'] ?? '';
$phone = $data['phone'] ?? '';
$code = $data['code'] ?? '';

// ========== 新增：短信验证码校验 ==========
if (!$phone || !$code || !preg_match('/^1\d{10}$/', $phone) || !preg_match('/^\d{6}$/', $code)) {
    echo json_encode(['ok'=>false, 'msg'=>'手机号或验证码格式错误']);
    exit;
}
if (!isset($_SESSION['sms_verify_code']) ||
    $_SESSION['sms_verify_code'] != $code ||
    $_SESSION['sms_verify_phone'] != $phone ||
    $_SESSION['sms_verify_time'] + 300 < time())
{
    echo json_encode(['ok'=>false, 'msg'=>'短信验证码无效或已过期']);
    exit;
}

// ========== 继续后续签署流程 ==========

if (!$uuid || !$imgBase64 || strpos($imgBase64, 'data:image/png;base64,') !== 0) {
    echo json_encode(['ok'=>false, 'msg'=>'参数错误']);
    exit;
}

// 保存为本地图片文件
$imgData = base64_decode(str_replace('data:image/png;base64,','',$imgBase64));
$saveDir = __DIR__.'/signatures/';
if (!is_dir($saveDir)) mkdir($saveDir,0777,true);
$filename = $saveDir . 'sign_' . $row['id'] . '_' . time() . '.png';
file_put_contents($filename, $imgData);
$relativePath = 'signatures/' . basename($filename);
$sign_date = date('Y-m-d');

// 生成合同编号
function generate_contract_no($db) {
    $prefix = 'HT'.date('Ymd');
    $today = date('Y-m-d');
    $count = $db->querySingle("SELECT COUNT(*) FROM contracts_agreement WHERE sign_date=:today", false, [':today' => $today]);
    if ($count === false) $count = 0;
    $serial = str_pad($count+1, 3, '0', STR_PAD_LEFT);
    return $prefix.$serial;
}
$contract_no = $row['contract_no'] ?? '';
if (!$contract_no) {
    $contract_no = generate_contract_no($db);
}

// 获取服务期、分段（参数绑定）
$period = null;
if ($row['service_period_id']) {
    $stmt_period = $db->prepare("SELECT * FROM service_periods WHERE id=:id");
    $stmt_period->bindValue(':id', $row['service_period_id'], SQLITE3_INTEGER);
    $period = $stmt_period->execute()->fetchArray(SQLITE3_ASSOC);
}
$segment = null;
if ($row['service_segment_id']) {
    $stmt_segment = $db->prepare("SELECT * FROM service_segments WHERE id=:id");
    $stmt_segment->bindValue(':id', $row['service_segment_id'], SQLITE3_INTEGER);
    $segment = $stmt_segment->execute()->fetchArray(SQLITE3_ASSOC);
}
// 盖章
$seal_img = '';
if ($row['seal_id']) {
    $stmt_seal = $db->prepare("SELECT image_path FROM seal_templates WHERE id=:id");
    $stmt_seal->bindValue(':id', $row['seal_id'], SQLITE3_INTEGER);
    $seal = $stmt_seal->execute()->fetchArray(SQLITE3_ASSOC);
    if ($seal && file_exists($seal['image_path'])) $seal_img = $seal['image_path'];
}

// 变量
$vars = [
    'client_name'    => $row['client_name'] ?? '',
    'contact_person' => $row['contact_person'] ?? '',
    'contact_phone'  => $row['contact_phone'] ?? '',
    'contact_email'  => $row['contact_email'] ?? '',
    'remark'         => $row['remark'] ?? '',
    'service_start'  => $period['service_start'] ?? '',
    'service_end'    => $period['service_end'] ?? '',
    'month_count'    => $period['month_count'] ?? '',
    'package_type'   => $period['package_type'] ?? '',
    'price_per_year' => $segment ? ($segment['price_per_year'] ?? '') : ($period['price_per_year'] ?? ''),
    'segment_fee'    => $segment['segment_fee'] ?? '',
    'sign_date'      => $sign_date,
    'sign_year'      => date('Y', strtotime($sign_date)),
    'sign_month'     => date('m', strtotime($sign_date)),
    'sign_day'       => date('d', strtotime($sign_date)),
    'contract_no'    => $contract_no
];

// 渲染最终快照（签名、盖章、编号）
function render_contract_template($tpl, $vars, $seal_img = '', $signature_img = '') {
    if ($seal_img && strpos($tpl, '{seal}') !== false) {
        $tpl = str_replace('{seal}', '<img src="' . $seal_img . '" style="height:60px;">', $tpl);
    }
    if ($signature_img && strpos($tpl, '{signature}') !== false) {
        $tpl = str_replace('{signature}', '<img src="' . $signature_img . '" style="height:60px;">', $tpl);
    }
    foreach ($vars as $k => $v) $tpl = str_replace('{'.$k.'}', htmlspecialchars($v), $tpl);
    return $tpl;
}
$snapshot_html = render_contract_template($row['template_content'], $vars, $seal_img, $relativePath);

// 生成哈希
$contract_hash = hash('sha256', $snapshot_html);

// 合同底部拼接编号、哈希、查验二维码
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$check_url = $protocol . $host . '/contract_verify.php?no=' . urlencode($contract_no);

$snapshot_html .= "<hr style='border:1px dashed #bbb; margin:20px 0;'>
<div style='font-size:15px;color:#666;'>
合同编号：{$contract_no}<br>
合同哈希：{$contract_hash}<br>
<span>扫码查验真伪：</span>
<img src='qrcode.php?text={$check_url}' height='80'>
</div>";

$sign_ip = $_SERVER['REMOTE_ADDR'];

// 更新合同，增加签署日期和最终快照、编号、哈希、签署IP/手机号
$stmt2 = $db->prepare("UPDATE contracts_agreement SET sign_image=:sign_image, sign_date=:sign_date, content_snapshot=:content_snapshot, contract_no=:contract_no, contract_hash=:contract_hash, sign_ip=:sign_ip, sign_phone=:sign_phone WHERE uuid=:uuid");
$stmt2->bindValue(':sign_image', $relativePath, SQLITE3_TEXT);
$stmt2->bindValue(':sign_date', $sign_date, SQLITE3_TEXT);
$stmt2->bindValue(':content_snapshot', $snapshot_html, SQLITE3_TEXT);
$stmt2->bindValue(':contract_no', $contract_no, SQLITE3_TEXT);
$stmt2->bindValue(':contract_hash', $contract_hash, SQLITE3_TEXT);
$stmt2->bindValue(':sign_ip', $sign_ip, SQLITE3_TEXT);
$stmt2->bindValue(':sign_phone', $phone, SQLITE3_TEXT);
$stmt2->bindValue(':uuid', $uuid, SQLITE3_TEXT);
$stmt2->execute();

echo json_encode(['ok'=>true]);
?>
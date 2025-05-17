<?php
session_start();

// ==== 配置 ====
$yunpian_apikey = '你的云片apikey'; // 替换为你的云片apikey
$sign = '115科技'; // 必须和云片平台审核通过的签名一致
$interval_seconds = 60; // 同手机号最小间隔
$ip_limit_hour = 2;    // 每个IP每小时最多几次

$phone = $_POST['phone'] ?? '';
if (!preg_match('/^1\d{10}$/', $phone)) {
    exit(json_encode(['ok'=>0, 'msg'=>'手机号格式不正确']));
}

// ==== 防刷1：同手机号60秒内只能发一次 ====
if (isset($_SESSION['sms_last_send'][$phone]) && time() - $_SESSION['sms_last_send'][$phone] < $interval_seconds) {
    exit(json_encode(['ok'=>0, 'msg'=>'发送过于频繁，请稍后再试']));
}

// ==== 防刷2：同IP每小时最多2次 ====
$ip = $_SERVER['REMOTE_ADDR'];
if (!isset($_SESSION['sms_ip_count'])) $_SESSION['sms_ip_count'] = [];
// 清理过期
foreach ($_SESSION['sms_ip_count'] as $k => $v) {
    if ($v['time'] < time() - 3600) unset($_SESSION['sms_ip_count'][$k]);
}
if (!isset($_SESSION['sms_ip_count'][$ip])) {
    $_SESSION['sms_ip_count'][$ip] = ['count'=>0, 'time'=>time()];
}
if ($_SESSION['sms_ip_count'][$ip]['count'] >= $ip_limit_hour) {
    exit(json_encode(['ok'=>0, 'msg'=>'发送已达上限，请稍后再试']));
}
$_SESSION['sms_ip_count'][$ip]['count']++;

// ==== 5分钟内重复请求同手机号，返回原验证码 ====
if (isset($_SESSION['sms_verify_phone']) && $_SESSION['sms_verify_phone']===$phone
    && isset($_SESSION['sms_verify_time']) && time()-$_SESSION['sms_verify_time'] < 300
    && isset($_SESSION['sms_verify_code'])
) {
    $code = $_SESSION['sms_verify_code'];
} else {
    $code = rand(100000, 999999);
    $_SESSION['sms_verify_code'] = $code;
    $_SESSION['sms_verify_phone'] = $phone;
    $_SESSION['sms_verify_time'] = time();
}

// ==== 记录发送时间 ====
if (!isset($_SESSION['sms_last_send'])) $_SESSION['sms_last_send'] = [];
$_SESSION['sms_last_send'][$phone] = time();

// 【重点：短信内容格式必须是【签名】+内容】
$text = "【{$sign}】您的验证码是{$code}，5分钟内有效。";

// ==== 调用云片API ====
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://sms.yunpian.com/v2/sms/single_send.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'apikey' => $yunpian_apikey,
    'mobile' => $phone,
    'text' => $text
]));
$output = curl_exec($ch);
curl_close($ch);

$res = json_decode($output, 1);
if ($res && $res['code'] == 0) {
    echo json_encode(['ok'=>1]);
} else {
    $errmsg = $res && isset($res['msg']) ? $res['msg'] : '短信发送失败';
    echo json_encode(['ok'=>0, 'msg'=>$errmsg]);
}
?>
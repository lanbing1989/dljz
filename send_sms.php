<?php
session_start();

// 配置
$yunpian_apikey = '你的云片apikey'; // 替换为你的云片apikey

$phone = $_POST['phone'] ?? '';
if (!preg_match('/^1\d{10}$/', $phone)) {
    exit(json_encode(['ok'=>0, 'msg'=>'手机号格式不正确']));
}

$code = rand(100000, 999999);
$_SESSION['sms_verify_code'] = $code;
$_SESSION['sms_verify_phone'] = $phone;
$_SESSION['sms_verify_time'] = time();

$text = "【你的签名】您的验证码是{$code}，5分钟内有效。";

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
    echo json_encode(['ok'=>0, 'msg'=>'短信发送失败：'.$res['msg']]);
}
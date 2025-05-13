<?php
session_start();
$phone = $_POST['phone'] ?? '';
$code  = $_POST['code'] ?? '';
if (!isset($_SESSION['sms_verify_code']) ||
    $_SESSION['sms_verify_code'] != $code ||
    $_SESSION['sms_verify_phone'] != $phone ||
    $_SESSION['sms_verify_time'] + 300 < time())
{
    echo json_encode(['ok'=>0, 'msg'=>'短信验证码无效或已过期']);
} else {
    echo json_encode(['ok'=>1]);
}
?>
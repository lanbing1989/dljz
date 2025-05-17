<?php
// 启用更安全的Session配置，防止常见攻击
ini_set('session.cookie_httponly', 1); // 让JS无法读取cookie
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 1 : 0); // HTTPS下启用secure
ini_set('session.use_strict_mode', 1); // 防止Session固定攻击

session_start();

// 防止Session固定攻击：首次登录时更换Session ID
if (empty($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

/**
 * 防止Session劫持（可选增强，绑定部分IP和User-Agent）
 * 这样即使session_id被盗用，也很难复用
 */
if (!empty($_SESSION['user_id'])) {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'none';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'none';
    $ua_hash = hash('sha256', $ua);
    // 只用IP前三段，避免部分宽带用户频繁变动导致误杀
    $ip_part = substr($ip, 0, strrpos($ip, '.'));
    if (!isset($_SESSION['ua_hash'])) {
        $_SESSION['ua_hash'] = $ua_hash;
        $_SESSION['ip_part'] = $ip_part;
    } else {
        // 检查当前请求是否与登录时的一致
        if ($_SESSION['ua_hash'] !== $ua_hash || $_SESSION['ip_part'] !== $ip_part) {
            // 如不一致则销毁Session并强制重新登录
            session_unset();
            session_destroy();
            header('Location: login.php');
            exit;
        }
    }
}

// 判断是否已登录，未登录则跳转登录页
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/**
 * 如果有多角色权限需求，可以在此处增加角色判断
 * 例如：
 * if ($_SESSION['role'] !== 'admin') {
 *     // 不具备权限，跳转或报错
 *     header('Location: no_permission.php');
 *     exit;
 * }
 */

?>
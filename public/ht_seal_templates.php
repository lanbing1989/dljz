<?php
require 'auth.php';
require 'db.php';

// 删除签章
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $stmt1 = $db->prepare("SELECT image_path FROM seal_templates WHERE id=:id");
    $stmt1->bindValue(':id', $id, SQLITE3_INTEGER);
    $row = $stmt1->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row && file_exists($row['image_path'])) unlink($row['image_path']);
    $stmt2 = $db->prepare("DELETE FROM seal_templates WHERE id=:id");
    $stmt2->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt2->execute();
    header("Location: ht_seal_templates.php");
    exit;
}

// 新增签章
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['seal'])) {
    $name = trim($_POST['name']);
    $tmp = $_FILES['seal']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['seal']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
        $fname = "seals/seal_tpl_".uniqid().".$ext";
        @mkdir('seals', 0777, true);
        move_uploaded_file($tmp, $fname);
        $stmt = $db->prepare("INSERT INTO seal_templates (name, image_path, created_at) VALUES (:n, :i, :t)");
        $stmt->bindValue(':n', $name, SQLITE3_TEXT);
        $stmt->bindValue(':i', $fname, SQLITE3_TEXT);
        $stmt->bindValue(':t', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();
        header("Location: ht_seal_templates.php");
        exit;
    } else {
        $msg = "只允许PNG/JPG格式";
    }
}

$res = $db->query("SELECT * FROM seal_templates ORDER BY id DESC");
$seals = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) $seals[] = $row;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"><title>签章管理</title>
    <link href="/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <script src="/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php include('navbar.php');?>
<div class="container mt-4">
    <h4>签章管理</h4>
    <?php if (!empty($msg)) echo "<div class='alert alert-danger'>".htmlspecialchars($msg)."</div>"; ?>
    <form class="row g-3 mb-4" method="post" enctype="multipart/form-data">
        <div class="col-auto">
            <input type="text" name="name" class="form-control" placeholder="签章名称" required>
        </div>
        <div class="col-auto">
            <input type="file" name="seal" accept=".png,.jpg,.jpeg" required>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">上传签章</button>
        </div>
    </form>
    <table class="table table-bordered bg-white">
        <thead><tr><th>ID</th><th>名称</th><th>签章图片</th><th>上传时间</th><th>操作</th></tr></thead>
        <tbody>
            <?php foreach($seals as $s): ?>
            <tr>
                <td><?=htmlspecialchars($s['id'])?></td>
                <td><?=htmlspecialchars($s['name'])?></td>
                <td><?php if ($s['image_path'] && file_exists($s['image_path'])): ?>
                    <img src="<?=htmlspecialchars($s['image_path'])?>" style="max-height:60px;">
                    <?php endif;?></td>
                <td><?=htmlspecialchars($s['created_at'])?></td>
                <td>
                    <a href="ht_seal_templates.php?del=<?=urlencode($s['id'])?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除？')">删除</a>
                </td>
            </tr>
            <?php endforeach;?>
        </tbody>
    </table>
</div>
</body>
</html>
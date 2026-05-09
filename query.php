<?php
require 'db_config.php';
$results = [];
$searched = false;

// 获取当前激活账套名
$active_event = $pdo->query("SELECT event_name, id FROM events WHERE is_active=1")->fetch();

if (isset($_POST['do_query'])) {
    $stmt = $pdo->prepare("SELECT r.*, c.category_name 
                           FROM registrations r 
                           JOIN categories c ON r.category_id = c.id 
                           WHERE r.job_number = ? AND r.real_name = ? AND c.event_id = ?");
    $stmt->execute([$_POST['job_num'], $_POST['user_name'], $active_event['id']]);
    $results = $stmt->fetchAll();
    $searched = true;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60"> <!-- 业内标准：查询页60秒自动刷新 -->
    <title>报名结果查询 - <?=$active_event['event_name']?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 600px;">
    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white text-center py-3">
            <h5 class="mb-0">报名情况查询</h5>
            <small><?=$active_event['event_name']?></small>
        </div>
        <div class="card-body p-4">
            <form method="POST" class="mb-4">
                <div class="mb-3">
                    <input type="text" name="job_num" class="form-control form-control-lg" placeholder="请输入您的工号" required>
                </div>
                <div class="mb-3">
                    <input type="text" name="user_name" class="form-control form-control-lg" placeholder="请输入您的姓名" required>
                </div>
                <button name="do_query" class="btn btn-primary btn-lg w-100">立即查询</button>
            </form>

            <?php if ($searched): ?>
                <hr>
                <?php if (count($results) > 0): ?>
                    <div class="list-group">
                        <?php foreach($results as $r): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 fw-bold"><?=$r['category_name']?></h6>
                                    <small class="text-muted"><?=date('m-d H:i', strtotime($r['create_time']))?></small>
                                </div>
                                <p class="mb-1">状态：
                                    <?php if($r['status'] == 1): ?>
                                        <span class="badge bg-success">报名成功</span>
                                    <?php elseif($r['status'] == 0): ?>
                                        <span class="badge bg-warning text-dark">待管理员审核</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">未通过/名额已满</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">未查到相关报名记录，请核对信息或先前往报名。</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center">
            <a href="index.php" class="btn btn-link btn-sm text-decoration-none">返回报名首页</a>
        </div>
    </div>
</div>
</body>
</html>
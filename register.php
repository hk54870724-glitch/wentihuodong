<?php
require 'db_config.php';

$msg = "";
$error = "";
$cat_id = $_GET['cat_id'] ?? null;

// 1. 获取当前激活账套及配置
$active_event = $pdo->query("SELECT e.*, c.personal_limit FROM events e LEFT JOIN config c ON e.id = c.event_id WHERE e.is_active=1")->fetch();
$event_id = $active_event['id'];

// 2. 获取当前申请组别的详细信息
if (!$cat_id) {
    header("Location: index.php");
    exit;
}
$stmt_cat = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND event_id = ?");
$stmt_cat->execute([$cat_id, $event_id]);
$category = $stmt_cat->fetch();

if (!$category) {
    die("该项目不存在或不属于当前赛事。");
}

// 3. 处理报名提交
if (isset($_POST['submit_reg'])) {
    $job_number = strtoupper(trim($_POST['job_number']));
    $real_name = trim($_POST['real_name']);
    $phone = trim($_POST['phone']);

    // --- 校验逻辑 A：白名单匹配 (修正版) ---
    // 必须确保该工号是在当前激活账套 (event_id) 的白名单中
    $check_white = $pdo->prepare("SELECT * FROM whitelist WHERE job_number = ? AND name = ? AND event_id = ?");
    $check_white->execute([$job_number, $real_name, $event_id]);
    $white_user = $check_white->fetch();

    if (!$white_user) {
        $error = "错误：工号或姓名不在本场准入名单中。";
    } else {
        // --- 校验逻辑 B：重复报名检查 ---
        $check_dup = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE category_id = ? AND job_number = ?");
        $check_dup->execute([$cat_id, $job_number]);
        if ($check_dup->fetchColumn() > 0) {
            $error = "您已报名过此项目，请勿重复提交。";
        } else {
            // --- 校验逻辑 C：个人总限报项数检查 ---
            $check_limit = $pdo->prepare("SELECT COUNT(*) FROM registrations r JOIN categories c ON r.category_id = c.id WHERE r.job_number = ? AND c.event_id = ?");
            $check_limit->execute([$job_number, $event_id]);
            $current_signed = $check_limit->fetchColumn();

            if ($current_signed >= $active_event['personal_limit']) {
                $error = "报名失败：您在本场赛事中最多只能参加 " . $active_event['personal_limit'] . " 个项目。";
            } else {
                // --- 执行报名逻辑 ---
                // 判断是否还有名额，若有名额则状态为1（成功），否则为0（待审）
                $status = ($category['current_count'] < $category['max_limit']) ? 1 : 0;
                
                try {
                    $pdo->beginTransaction();
                    
                    $ins = $pdo->prepare("INSERT INTO registrations (category_id, job_number, real_name, phone, status, create_time) VALUES (?, ?, ?, ?, ?, NOW())");
                    $ins->execute([$cat_id, $job_number, $real_name, $phone, $status]);

                    // 如果报名成功（非待审），更新 categories 表的计数器
                    if ($status == 1) {
                        $upd = $pdo->prepare("UPDATE categories SET current_count = current_count + 1 WHERE id = ?");
                        $upd->execute([$cat_id]);
                    }

                    $pdo->commit();
                    $msg = $status == 1 ? "报名成功！" : "名额已满，您的申请已进入后台待审核列表。";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "系统错误，请稍后再试。";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>项目报名 - <?=$category['category_name']?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .reg-card { max-width: 500px; margin: 50px auto; border-radius: 15px; border: none; }
        .btn-reg { padding: 12px; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow reg-card">
        <div class="card-header bg-white py-3 text-center border-0">
            <h4 class="fw-bold mb-0 text-primary"><?=$category['category_name']?></h4>
            <small class="text-muted">正在进行报名登记</small>
        </div>
        <div class="card-body p-4">
            
            <?php if($msg): ?>
                <div class="alert alert-success text-center py-4">
                    <h5 class="alert-heading">恭喜！</h5>
                    <p class="mb-3"><?=$msg?></p>
                    <a href="index.php" class="btn btn-success btn-sm">返回首页</a>
                    <a href="query.php" class="btn btn-outline-success btn-sm">查询状态</a>
                </div>
            <?php else: ?>

                <?php if($error): ?>
                    <div class="alert alert-danger small mb-3"><?=$error?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">工号</label>
                        <input type="text" name="job_number" class="form-control" placeholder="请输入您的工号" required value="<?=htmlspecialchars($_POST['job_number'] ?? '')?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">姓名</label>
                        <input type="text" name="real_name" class="form-control" placeholder="请输入白名单登记的姓名" required value="<?=htmlspecialchars($_POST['real_name'] ?? '')?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">手机号码</label>
                        <input type="tel" name="phone" class="form-control" placeholder="用于紧急通知接收" required value="<?=htmlspecialchars($_POST['phone'] ?? '')?>">
                    </div>

                    <div class="alert alert-light border small text-muted">
                        <i class="bi bi-info-circle"></i> 温馨提示：<br>
                        1. 请确保信息与公司系统一致。<br>
                        2. 当前项目最大名额：<?=$category['max_limit']?> 人。<br>
                        3. 您本场最多可报：<?=$active_event['personal_limit']?> 项。
                    </div>

                    <button type="submit" name="submit_reg" class="btn btn-primary w-100 btn-reg">提交报名</button>
                    <a href="index.php" class="btn btn-link w-100 text-decoration-none mt-2 text-muted">取消并返回</a>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>
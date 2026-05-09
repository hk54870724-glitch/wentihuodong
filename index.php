<?php
require 'db_config.php';

// 获取当前激活账套
$active_event = $pdo->query("SELECT * FROM events WHERE is_active=1")->fetch();
$event_id = $active_event['id'];

// 获取当前账套下的所有组别
$stmt = $pdo->prepare("SELECT * FROM categories WHERE event_id = ?");
$stmt->execute([$event_id]);
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60"> <!-- 首页60秒自动刷新，同步名额动态 -->
    <title><?=$active_event['event_name']?> - 报名入口</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .category-card { transition: transform 0.2s; border-radius: 12px; }
        .category-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .nav-link-custom { font-weight: bold; color: #0d6efd !important; }
    </style>
</head>
<body class="bg-light">

<!-- 顶部导航栏 -->
<nav class="navbar navbar-expand-lg navbar-white bg-white shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">赛事报名系统</a>
        <div class="d-flex">
            <!-- 核心：增加查询页面链接 -->
            <a href="query.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                🔍 查询我的报名结果
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="text-center mb-5">
        <h2 class="fw-bold"><?=$active_event['event_name']?></h2>
        <p class="text-muted">请选择您要参加的项目并完成信息登记</p>
    </div>

    <div class="row g-4">
        <?php foreach($categories as $c): ?>
        <?php 
            $now = date('Y-m-d H:i:s');
            $is_open = ($now >= $c['start_time'] && $now <= $c['end_time']);
            $is_full = ($c['current_count'] >= $c['max_limit']);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm category-card">
                <div class="card-body">
                    <h5 class="card-title fw-bold"><?=$c['category_name']?></h5>
                    <div class="mb-3">
                        <span class="badge <?=$is_full ? 'bg-danger' : 'bg-success'?>">
                            名额：<?=$c['current_count']?> / <?=$c['max_limit']?>
                        </span>
                        <?php if(!$is_open): ?>
                            <span class="badge bg-secondary">不在报名时间内</span>
                        <?php endif; ?>
                    </div>
                    <p class="small text-muted mb-4">
                        时间：<?=date('m/d H:i', strtotime($c['start_time']))?> 至 <?=date('m/d H:i', strtotime($c['end_time']))?>
                    </p>
                    
                    <?php if($is_open): ?>
                        <a href="register.php?cat_id=<?=$c['id']?>" class="btn <?=$is_full ? 'btn-outline-warning' : 'btn-primary'?> w-100">
                            <?=$is_full ? '进入候补/待审' : '立即报名'?>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100" disabled>暂未开启</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <footer class="mt-5 py-4 text-center text-muted border-top">
        <p class="small">© 2026 企业赛事管理系统 | 页面每 60 秒自动刷新名额</p>
    </footer>
</div>

</body>
</html>
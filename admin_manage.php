<?php
require 'db_config.php';
$msg = "";

// 1. 核心状态获取：当前激活账套及限额
$active_event = $pdo->query("SELECT e.*, c.personal_limit FROM events e LEFT JOIN config c ON e.id = c.event_id WHERE e.is_active=1")->fetch();
$event_id = $active_event['id'];

// 2. 锁定检查：判断当前账套是否有任何报名记录
$check_reg = $pdo->prepare("SELECT COUNT(*) FROM registrations r JOIN categories c ON r.category_id = c.id WHERE c.event_id = ?");
$check_reg->execute([$event_id]);
$has_data = ($check_reg->fetchColumn() > 0);

// --- 逻辑处理：账套与全局配置 ---

// A1. 修改当前账套基础信息 (限无人报名时)
if (isset($_POST['edit_event_config'])) {
    if (!$has_data) {
        $pdo->prepare("UPDATE events SET event_name = ? WHERE id = ?")->execute([$_POST['ev_name'], $event_id]);
        $pdo->prepare("UPDATE config SET personal_limit = ? WHERE event_id = ?")->execute([$_POST['ev_limit'], $event_id]);
        header("Location: admin_manage.php?m=账套更新成功"); exit;
    } else { $msg = "已有报名数据，账套配置已锁定"; }
}

// A2. 开启新账套
if (isset($_POST['create_new_event'])) {
    $pdo->query("UPDATE events SET is_active = 0");
    $stmt = $pdo->prepare("INSERT INTO events (event_name, is_active) VALUES (?, 1)");
    $stmt->execute([$_POST['n_ev_name']]);
    $new_id = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO config (event_id, personal_limit) VALUES (?, ?)")->execute([$new_id, $_POST['n_ev_limit']]);
    header("Location: admin_manage.php"); exit;
}

// --- 逻辑处理：组别管理 ---
if (isset($_POST['save_cat'])) {
    if (!empty($_POST['cat_id'])) {
        $sql = "UPDATE categories SET category_name=?, max_limit=?, start_time=?, end_time=? WHERE id=? AND event_id=?";
        $pdo->prepare($sql)->execute([$_POST['cn'], $_POST['ml'], $_POST['st'], $_POST['et'], $_POST['cat_id'], $event_id]);
    } else {
        $sql = "INSERT INTO categories (event_id, category_name, max_limit, start_time, end_time) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$event_id, $_POST['cn'], $_POST['ml'], $_POST['st'], $_POST['et']]);
    }
}

if (isset($_GET['del_cat'])) {
    $pdo->prepare("DELETE FROM categories WHERE id = ? AND current_count = 0")->execute([$_GET['del_cat']]);
}

// --- 逻辑处理：白名单管理 ---
if (isset($_POST['save_white'])) {
    if (!empty($_POST['white_id'])) {
        $pdo->prepare("UPDATE whitelist SET name=?, department=? WHERE id=? AND event_id=?")->execute([$_POST['w_name'], $_POST['w_dept'], $_POST['white_id'], $event_id]);
    } else {
        $pdo->prepare("INSERT INTO whitelist (event_id, job_number, name, department) VALUES (?, ?, ?, ?)")->execute([$event_id, $_POST['w_job'], $_POST['w_name'], $_POST['w_dept']]);
    }
}

if (isset($_POST['imp_white']) && $_FILES['csv']['tmp_name']) {
    $handle = fopen($_FILES['csv']['tmp_name'], "r"); fgetcsv($handle);
    $pdo->beginTransaction();
    $ins = $pdo->prepare("INSERT INTO whitelist (event_id, job_number, name, department) VALUES (?, ?, ?, ?)");
    while ($d = fgetcsv($handle, 1000, ",")) { if(count($d)>=3) $ins->execute([$event_id, trim($d[0]), trim($d[1]), trim($d[2])]); }
    $pdo->commit();
}

if (isset($_GET['del_white'])) { $pdo->prepare("DELETE FROM whitelist WHERE id=? AND event_id=?")->execute([$_GET['del_white'], $event_id]); }

// --- 数据加载 ---
$categories = $pdo->prepare("SELECT * FROM categories WHERE event_id = ?"); $categories->execute([$event_id]);
$cat_list = $categories->fetchAll();

$search_w = $_GET['search_w'] ?? '';
$w_sql = "SELECT * FROM whitelist WHERE event_id = :event_id";
$params = [':event_id' => $event_id];

if ($search_w) {
    $w_sql .= " AND (job_number LIKE :s OR name LIKE :s)";
    $params[':s'] = "%$search_w%";
}

$w_stmt = $pdo->prepare($w_sql);
$w_stmt->execute($params);
$whitelist = $w_stmt->fetchAll();

$regs = $pdo->prepare("SELECT r.*, c.category_name FROM registrations r JOIN categories c ON r.category_id = c.id WHERE c.event_id = ? ORDER BY r.create_time DESC");
$regs->execute([$event_id]); $reg_list = $regs->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><title>后台管理 V2.3 Final</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>.btn-xs{padding:2px 5px; font-size:12px;}</style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
    <div class="row g-3">
        <!-- 左栏：账套配置与组别 -->
        <div class="col-md-3">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span class="small fw-bold">当前账套设置</span>
                    <?php if(!$has_data): ?>
                        <button class="btn btn-warning btn-xs" data-bs-toggle="modal" data-bs-target="#editEventModal">修改</button>
                    <?php else: ?>
                        <span class="badge bg-secondary" style="font-size:10px">已锁定</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <h5 class="mb-1 text-primary"><?=$active_event['event_name']?></h5>
                    <p class="small text-muted mb-2">个人限报：<strong><?=$active_event['personal_limit']?></strong> 项</p>
                    <button class="btn btn-outline-dark btn-sm w-100" data-bs-toggle="modal" data-bs-target="#newEventModal">开启新赛季</button>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-bold small d-flex justify-content-between align-items-center">
                    项目组别
                    <button class="btn btn-primary btn-xs" onclick="clearCatForm()" data-bs-toggle="modal" data-bs-target="#catModal">+ 新增</button>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php foreach($cat_list as $c): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?=$c['category_name']?> <small>(<?=$c['current_count']?>/<?=$c['max_limit']?>)</small></span>
                            <div>
                                <a href="javascript:void(0)" onclick="editCat(<?=htmlspecialchars(json_encode($c))?>)" class="me-1">改</a>
                                <?php if($c['current_count']==0): ?><a href="?del_cat=<?=$c['id']?>" class="text-danger">删</a><?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 中栏：白名单管理 -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold small d-flex justify-content-between align-items-center">
                    准入白名单 (<?=$active_event['event_name']?>)
                    <button class="btn btn-success btn-xs" onclick="clearWhiteForm()" data-bs-toggle="modal" data-bs-target="#whiteModal">+ 单人</button>
                </div>
                <div class="p-2 border-bottom bg-light">
                    <form method="POST" enctype="multipart/form-data" class="input-group input-group-sm">
                        <input type="file" name="csv" class="form-control" required>
                        <button name="imp_white" class="btn btn-dark">导入CSV</button>
                    </form>
                    <!-- ===== 新增：白名单检索表单 ===== -->
                    <form method="GET" class="input-group input-group-sm mt-2">
                        <span class="input-group-text"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg></span>
                        <input type="text" name="search_w" class="form-control" placeholder="工号或姓名" value="<?=htmlspecialchars($search_w)?>">
                        <button class="btn btn-outline-secondary" type="submit">搜索</button>
                        <?php if($search_w): ?>
                        <a href="admin_manage.php" class="btn btn-outline-secondary">清除</a>
                        <?php endif; ?>
                    </form>
                    <!-- ===== 检索表单结束 ===== -->
                </div>
                <div class="table-responsive" style="max-height: 600px;">
                    <table class="table table-sm table-hover small">
                        <thead class="table-light"><tr><th>工号</th><th>姓名</th><th>部门</th><th>操作</th></tr></thead>
                        <tbody>
                            <?php foreach($whitelist as $w): ?>
                            <tr>
                                <td><?=$w['job_number']?></td><td><?=$w['name']?></td><td><?=$w['department']?></td>
                                <td>
                                    <a href="javascript:void(0)" onclick="editWhite(<?=htmlspecialchars(json_encode($w))?>)">改</a> |
                                    <a href="?del_white=<?=$w['id']?>" class="text-danger">删</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($whitelist)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">无匹配记录</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 右栏：监控 -->
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold small">报名监控实时视图</div>
                <div class="table-responsive">
                    <table class="table table-sm small">
                        <thead><tr><th>姓名</th><th>项目</th><th>状态</th><th>操作</th></tr></thead>
                        <tbody>
                            <?php foreach($reg_list as $r): ?>
                            <tr class="<?=$r['status']==1?'table-success':''?>">
                                <td><?=$r['real_name']?></td><td><?=$r['category_name']?></td>
                                <td><?=$r['status']==1?'成功':($r['status']==0?'待审':'驳回')?></td>
                                <td><?php if($r['status']==0): ?><a href="admin_approve.php?id=<?=$r['id']?>&act=pass" class="btn btn-xs btn-primary">通过</a><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 模态框：修改当前账套 -->
<div class="modal fade" id="editEventModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form class="modal-content" method="POST">
            <div class="modal-header"><h6>修改当前账套</h6></div>
            <div class="modal-body">
                <label class="small">账套名称</label>
                <input type="text" name="ev_name" class="form-control mb-2" value="<?=$active_event['event_name']?>" required>
                <label class="small">限报项目数</label>
                <input type="number" name="ev_limit" class="form-control" value="<?=$active_event['personal_limit']?>" required>
            </div>
            <div class="modal-footer"><button name="edit_event_config" class="btn btn-warning btn-sm w-100">确认更新</button></div>
        </form>
    </div>
</div>

<!-- 模态框：新增/编辑组别 -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header"><h6 id="catTitle">新增组别</h6></div>
            <div class="modal-body small">
                <input type="hidden" name="cat_id" id="cat_id">
                <label>项目名称</label><input type="text" name="cn" id="cat_name" class="form-control mb-2 form-control-sm" required>
                <label>名额上限</label><input type="number" name="ml" id="cat_limit" class="form-control mb-2 form-control-sm" required>
                <label>开始时间</label><input type="datetime-local" name="st" id="cat_st" class="form-control mb-2 form-control-sm" required>
                <label>结束时间</label><input type="datetime-local" name="et" id="cat_et" class="form-control mb-2 form-control-sm" required>
            </div>
            <div class="modal-footer"><button name="save_cat" class="btn btn-primary btn-sm">保存组别</button></div>
        </form>
    </div>
</div>

<!-- 模态框：新增/编辑白名单 -->
<div class="modal fade" id="whiteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form class="modal-content" method="POST">
            <div class="modal-header"><h6 id="wTitle">白名单人员</h6></div>
            <div class="modal-body small">
                <input type="hidden" name="white_id" id="w_id">
                <label>工号</label><input type="text" name="w_job" id="w_job" class="form-control mb-2 form-control-sm" required>
                <label>姓名</label><input type="text" name="w_name" id="w_name" class="form-control mb-2 form-control-sm" required>
                <label>部门</label><input type="text" name="w_dept" id="w_dept" class="form-control mb-2 form-control-sm" required>
            </div>
            <div class="modal-footer"><button name="save_white" class="btn btn-success btn-sm">确认保存</button></div>
        </form>
    </div>
</div>

<!-- 模态框：开启新赛季 (略) -->
<div class="modal fade" id="newEventModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST"><div class="modal-header"><h6>开启新赛季账套</h6></div><div class="modal-body small"><input type="text" name="n_ev_name" class="form-control mb-2" placeholder="如：2026秋季赛" required><input type="number" name="n_ev_limit" class="form-control" value="2" required></div><div class="modal-footer"><button name="create_new_event" class="btn btn-dark btn-sm">立即创建</button></div></form></div></div>

<script>
function editCat(d) {
    document.getElementById('catTitle').innerText = '编辑组别';
    document.getElementById('cat_id').value = d.id;
    document.getElementById('cat_name').value = d.category_name;
    document.getElementById('cat_limit').value = d.max_limit;
    document.getElementById('cat_st').value = d.start_time.replace(' ', 'T');
    document.getElementById('cat_et').value = d.end_time.replace(' ', 'T');
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
function clearCatForm() {
    document.getElementById('catTitle').innerText = '新增组别';
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_name').value = '';
    document.getElementById('cat_limit').value = '';
}
function editWhite(d) {
    document.getElementById('w_id').value = d.id;
    document.getElementById('w_job').value = d.job_number;
    document.getElementById('w_job').readOnly = true;
    document.getElementById('w_name').value = d.name;
    document.getElementById('w_dept').value = d.department;
    new bootstrap.Modal(document.getElementById('whiteModal')).show();
}
function clearWhiteForm() {
    document.getElementById('w_id').value = '';
    document.getElementById('w_job').value = '';
    document.getElementById('w_job').readOnly = false;
    document.getElementById('w_name').value = '';
    document.getElementById('w_dept').value = '';
}
</script>
</body>
</html>
<?php
require 'db_config.php';

$id = (int)$_GET['id'];
$action = $_GET['act'];

$pdo->beginTransaction();
try {
    $reg = $pdo->prepare("SELECT * FROM registrations WHERE id = ? FOR UPDATE");
    $reg->execute([$id]);
    $data = $reg->fetch();

    if ($action == 'pass') {
        // 人工强行通过需检查名额是否被占满
        $cat = $pdo->prepare("SELECT * FROM categories WHERE id = ? FOR UPDATE");
        $cat->execute([$data['category_id']]);
        $c = $cat->fetch();
        
        $pdo->prepare("UPDATE categories SET current_count = current_count + 1 WHERE id = ?")->execute([$data['category_id']]);
        $pdo->prepare("UPDATE registrations SET status = 1 WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("UPDATE registrations SET status = 2 WHERE id = ?")->execute([$id]);
    }
    
    $pdo->commit();
    header("Location: admin_manage.php");
} catch (Exception $e) {
    $pdo->rollBack();
    die("处理失败");
}
<?php
require 'db_config.php';

$active_event = $pdo->query("SELECT * FROM events WHERE is_active=1")->fetch();
$event_id = $active_event['id'];

$filename = "报名成功人员_".$active_event['event_name']."_".date('Ymd').".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// UTF-8 BOM 防止乱码
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, ['账套', '工号', '姓名', '部门', '组别', '手机', '报名时间']);

$sql = "SELECT e.event_name, r.job_number, r.real_name, r.department, c.category_name, r.phone, r.create_time 
        FROM registrations r 
        JOIN categories c ON r.category_id = c.id 
        JOIN events e ON c.event_id = e.id 
        WHERE e.id = ? AND r.status = 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$event_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}
fclose($output);
exit;
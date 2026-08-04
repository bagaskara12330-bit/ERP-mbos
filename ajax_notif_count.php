<?php
require_once 'koneksi.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

try {
    $stmt_log = $pdo->query("SELECT * FROM db_aktivitas_log ORDER BY id DESC LIMIT 15");
    $list_notif = $stmt_log->fetchAll(PDO::FETCH_ASSOC);
    $latest_id = count($list_notif) > 0 ? (int)$list_notif[0]['id'] : 0;
    
    $html = "";
    foreach($list_notif as $log) {
        $ikon = $log['ikon'] ? $log['ikon'] : '🔔';
        $waktu = date('d M Y, H:i', strtotime($log['waktu']));
        $html .= "<div class='notif-item'>
            <div class='notif-icon-circle'>{$ikon}</div>
            <div class='notif-text'><strong>@".htmlspecialchars($log['username'])."</strong> ".htmlspecialchars($log['aksi'])."<span class='notif-time'>{$waktu} WIB</span></div>
        </div>";
    }
    if (count($list_notif) == 0) {
        $html = "<div style='padding:20px; text-align:center; color:#94a3b8; font-size:12px;'>Belum ada aktivitas terekam.</div>";
    }
    
    header('Content-Type: application/json');
    echo json_encode(['latest_id' => $latest_id, 'html' => $html]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['latest_id' => 0, 'html' => '']);
}

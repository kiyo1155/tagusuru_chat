<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
require_once 'auth.php';
require_once 'db.php';
if(!isLoggedIn()){ http_response_code(401); exit; }

$user_id = $_SESSION['user_id'];
$last_check = $_SESSION['last_check'] ?? '1970-01-01 00:00:00';

// 案件の更新
$stmt = $pdo->prepare("
    SELECT id AS project_id, user_id, updated_at, 'project' AS type
    FROM projects
    WHERE updated_at > ? AND user_id != ?
");
$stmt->execute([$last_check, $user_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// チャットの更新
$stmt = $pdo->prepare("
    SELECT project_id, user_id, updated_at, 'chat' AS type
    FROM project_chats
    WHERE updated_at > ? AND user_id != ?
");
$stmt->execute([$last_check, $user_id]);
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 次回用に確認時刻を更新
$_SESSION['last_check'] = date('Y-m-d H:i:s');

// JSON返却
header('Content-Type: application/json');
echo json_encode(array_merge($projects, $chats));

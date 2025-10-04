<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';

header('Content-Type: application/json');

if(!isLoggedIn()){
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];
$project_id = $_GET['project_id'] ?? null;

if(!$project_id){
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.id, c.user_id, u.name AS user_name, c.message, c.edited, c.created_at
    FROM project_chats c
    JOIN users u ON c.user_id = u.id
    WHERE c.project_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$project_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);

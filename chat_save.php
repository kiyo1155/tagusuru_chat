<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';

header('Content-Type: application/json');

if(!isLoggedIn()){
    echo json_encode(['success'=>false,'message'=>'ログインしてください']);
    exit;
}

$user_id = $_SESSION['user_id'];

$project_id = $_POST['project_id'] ?? null;
$message = trim($_POST['message'] ?? '');
$edit_id = $_POST['edit_id'] ?? null;

if(!$project_id || !$message){
    echo json_encode(['success'=>false,'message'=>'必要な情報が不足しています']);
    exit;
}

try {
    if($edit_id){ 
        // 既存メッセージを編集
        // 編集したユーザーが投稿者本人か確認
        $stmt = $pdo->prepare("SELECT user_id FROM project_chats WHERE id=?");
        $stmt->execute([$edit_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row || $row['user_id'] != $user_id){
            echo json_encode(['success'=>false,'message'=>'編集権限がありません']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE project_chats SET message=?, edited=1, updated_at=NOW() WHERE id=?");
        $stmt->execute([$message, $edit_id]);
        echo json_encode(['success'=>true, 'id'=>$edit_id]);
    } else {
        // 新規メッセージ保存
        $stmt = $pdo->prepare("INSERT INTO project_chats (project_id,user_id,message) VALUES (?,?,?)");
        $stmt->execute([$project_id, $user_id, $message]);
        $id = $pdo->lastInsertId();
        echo json_encode(['success'=>true, 'id'=>$id]);
    }
} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>'DBエラー']);
}

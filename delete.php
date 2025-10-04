<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';
if(!isLoggedIn()){ exit(json_encode(['success'=>false])); }

$id = $_POST['id'] ?? null;
$user_id = $_SESSION['user_id'];

if($id){
    try {
        $pdo->beginTransaction();

        // 子テーブルを先に削除
        $stmt = $pdo->prepare("DELETE FROM project_chats WHERE project_id=?");
        $stmt->execute([$id]);

        // 親テーブルを削除
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id=? AND user_id=?");
        $res = $stmt->execute([$id,$user_id]);

        $pdo->commit();
        echo json_encode(['success'=>$res]);
    } catch (Exception $e){
        $pdo->rollBack();
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
} else {
    echo json_encode(['success'=>false]);
}
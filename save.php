<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

ini_set('display_errors',0);
ini_set('display_startup_errors',0);
error_reporting(0);

if(!isLoggedIn() || $_SERVER['REQUEST_METHOD']!=='POST'){
    echo json_encode(['success'=>false,'error'=>'ログインしていないかPOST以外']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;

$id = $_POST['id'] ?? null;
$name = $_POST['name'] ?: null;
$status = $_POST['status'] ?: null;
$vendor_company_id = $_POST['vendor_company_id'] ?: null; 
if($user_role==='vendor' && !$vendor_company_id){
    $vendor_company_id = $company_id; // vendor 自身の会社IDを使う
}
if($id && $user_role==='client' && !$vendor_company_id){
    $stmt = $pdo->prepare("SELECT vendor_company_id FROM projects WHERE id=?");
    $stmt->execute([$id]);
    $vendor_company_id_db = $stmt->fetchColumn();
    if($vendor_company_id_db !== false){
        $vendor_company_id = $vendor_company_id_db;
    }
}

$deadline = $_POST['deadline'] ?: null;
$delivery_location = $_POST['delivery_location'] ?: null;
$data_path = $_POST['data_path'] ?: null;

$vendorEditableStatuses = ['制作中','発送済','納品済'];

try {
    if($id){ // 更新
        if($user_role==='vendor'){
            if(!in_array($status,$vendorEditableStatuses)){
                echo json_encode(['success'=>false,'error'=>'このステータスには変更できません']);
                exit;
            }
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET name=?, status=?, vendor_company_id=?, deadline=?, delivery_location=?, data_path=?, updated_at=NOW()
                WHERE id=? AND vendor_company_id=?
            ");
            $res = $stmt->execute([$name, $status, $vendor_company_id, $deadline, $delivery_location, $data_path, $id, $company_id]);
        } else { // client
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET name=?, status=?, vendor_company_id=?, deadline=?, delivery_location=?, data_path=?, updated_at=NOW()
                WHERE id=? AND user_id=?
            ");
            $res = $stmt->execute([$name, $status, $vendor_company_id, $deadline, $delivery_location, $data_path, $id, $user_id]);
        }
        if(!$res) throw new Exception(implode(',', $stmt->errorInfo()));
        echo json_encode(['success'=>true,'id'=>$id,'name'=>$name,'status'=>$status]);

    } else { // 新規
        if($user_role==='vendor'){
            echo json_encode(['success'=>false,'error'=>'ベンダーは新規作成できません']);
            exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO projects (user_id,name,status,vendor_company_id,deadline,delivery_location,data_path,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
        ");
        $res = $stmt->execute([$user_id, $name, $status, $vendor_company_id, $deadline, $delivery_location, $data_path, date('Y-m-d H:i:s')]);
        if(!$res) throw new Exception(implode(',', $stmt->errorInfo()));
        $newId = $pdo->lastInsertId();
        echo json_encode(['success'=>true,'id'=>$newId,'name'=>$name,'status'=>$status]);
    }
} catch (Exception $e){
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

if($id && $user_role==='client'){
    // 既存案件を取得
    $stmt = $pdo->prepare("SELECT status FROM projects WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    $currentStatus = $stmt->fetchColumn();
    if($currentStatus && $currentStatus !== '保存中'){
        // 依頼済以降の案件は client が status を変更できない
        $status = $currentStatus;
    }
}
<?php
/**
 * auth.php
 * ログイン・ログアウト処理（role なし）
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ------------------------------------------------------------
// ログイン処理
// ------------------------------------------------------------
function login($email, $password) {
    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // セッションにユーザー情報をセット
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role']      = $user['role'];      
        $_SESSION['company_id'] = $user['company_id'];

        // セッション固定化対策
        session_regenerate_id(true);
        return true;
    }

    return false;
}

// ------------------------------------------------------------
// ログアウト処理
// ------------------------------------------------------------
function logout() {
    // セッション変数をクリア
    $_SESSION = [];
    // セッションを破棄
    if (session_id() !== '' || isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    redirect('login.php');
}

// ------------------------------------------------------------
// ログイン状態チェック
// ------------------------------------------------------------
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

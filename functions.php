<?php
/**
 * functions.php
 * 共通関数ファイル
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * 入力値のサニタイズ
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * ログインチェック
 * ログインしていなければ login.php にリダイレクト
 */
function checkLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * CSRF トークン生成
 */
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * CSRF トークン確認
 */
function checkCsrfToken($token) {
    if (empty($token) || $token !== $_SESSION[CSRF_TOKEN_NAME]) {
        die('不正なリクエストです。');
    }
}

/**
 * ユーザー情報取得
 */
function getUser($user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * 企業情報取得
 */
function getCompany($company_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$company_id]);
    return $stmt->fetch();
}

/**
 * メッセージの所有者確認
 */
function isMessageOwner($message_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id FROM messages WHERE id = ? AND user_id = ?');
    $stmt->execute([$message_id, $user_id]);
    return $stmt->fetch() ? true : false;
}

/**
 * リダイレクト処理
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * ログ出力（開発用）
 */
function logDebug($msg) {
    if (APP_DEBUG) {
        error_log($msg);
    }
}
<?php
/**
 * config.php
 * アプリケーション全体で使う設定を定義します。
 * - 本番では環境変数を利用してください。
 */

// --- 基本設定 ------------------------------------------------
// タイムゾーン
date_default_timezone_set('Asia/Tokyo');

// エラーレポート（開発時は true、本番は false）
define('APP_DEBUG', true);
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// --- パス系 --------------------------------------------------
// プロジェクトのルートディレクトリ（必要に応じて調整）
define('BASE_PATH', __DIR__);
// public ディレクトリの相対パス例（フロントの公開ディレクトリ）
define('PUBLIC_PATH', BASE_PATH . '/public');
// ロゴファイル
define('APP_LOGO', PUBLIC_PATH . 'logo.png'); // logo.png を public/images に配置

// --- セッション ------------------------------------------------
session_start();
// セッション固定化対策
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// --- データベース設定 ----------------------------------------
// 本番では getenv() などで環境変数から取得することを推奨
define('DB_HOST', getenv('DB_HOST') ?: 'mysql80.tagusuru.sakura.ne.jp');
define('DB_NAME', getenv('DB_NAME') ?: 'tagusuru_chat');
define('DB_USER', getenv('DB_USER') ?: 'tagusuru_chat');
define('DB_PASS', getenv('DB_PASS') ?: 'kiyo0904');
define('DB_CHARSET', 'utf8mb4');

// --- セキュリティ関連 ----------------------------------------
// パスワードハッシュオプション
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);
// CSRF トークン名
define('CSRF_TOKEN_NAME', 'csrf_token');

// --- その他設定 ----------------------------------------------
// 1ページあたりの一覧件数
define('PAGINATION_LIMIT', 20);

// 連想配列で設定を返すユーティリティ（必要なら require で呼び出せます）
return [
    'debug' => APP_DEBUG,
    'paths' => [
        'base' => BASE_PATH,
        'public' => PUBLIC_PATH,
        'logo' => APP_LOGO,
    ],
    'db' => [
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'pass' => DB_PASS,
        'charset' => DB_CHARSET,
    ],
    'security' => [
        'password_algo' => PASSWORD_HASH_ALGO,
        'csrf_token_name' => CSRF_TOKEN_NAME,
    ],
    'pagination_limit' => PAGINATION_LIMIT,
];

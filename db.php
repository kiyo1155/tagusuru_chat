<?php
/**
 * db.php
 * PDO を使用したデータベース接続処理
 */

require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // 本番ではユーザーに詳細を表示しない
    if (APP_DEBUG) {
        echo 'DB接続エラー: ' . $e->getMessage();
    } else {
        echo 'データベース接続に失敗しました。';
    }
    exit;
}

return $pdo;

<?php
require_once __DIR__ . '/config.php';

function getDbConnection(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verify_token = ? AND is_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        $message = "本登録が完了しました。ログインしてください。";
    } else {
        $message = "このリンクは無効です。";
    }
} else {
    $message = "トークンが見つかりません。";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><title>認証結果</title></head>
<body>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <a href="login.php">ログインページへ</a>
</body>
</html>

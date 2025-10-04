<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php'); // 共通ダッシュボードにリダイレクト
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken($_POST[CSRF_TOKEN_NAME]);

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($email, $password)) {
        redirect('dashboard.php'); // 共通ダッシュボード
    } else {
        $errors[] = 'メールアドレスまたはパスワードが正しくありません。';
    }
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link rel="stylesheet" href="reset.css">
    <link rel="stylesheet" href="message.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* ヘッダー */
        .header { display: flex; align-items: center; padding: 10px 24px; background: #000; }
        .header img { width: 120px; height: 120px; object-fit: contain; }
        /* トップテキスト中央寄せ */
        .top { background: #f9f9f9; padding: 60px 5vw; text-align: center; }
        /* フォーム */
        .contentbox { max-width: 400px; margin: 40px auto 0; padding: 0 16px; }
        .content { display: flex; flex-direction: column; margin-bottom: 20px; }
        .content p { font-weight: 700; margin-bottom: 8px; text-align: center; }
        .textarea { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; }
        .textarea:focus { border-color: #000; outline: none; }
        .send { margin-bottom: 40px; text-align: center; } /* ボタンとfooterの間の余白 */
        .send button { width: 100%; padding: 12px 0; border-radius: 8px; font-size: 1rem; font-weight: 700; }
        .send p { margin-top: 12px; font-size: 0.95rem; }
        .send a { text-decoration: underline; color: #000; }
        /* フッター */
        .footer { background: #000; color: #fff; text-align: center; padding: 20px 5vw; border-top: 1px solid #222; }
        .footertext { margin: 0; font-size: .9rem; color: #ccc; }
        /* レスポンシブ */
        @media (max-width: 640px) {
            .header img { width: 80px; height: 80px; }
            .top { padding: 40px 5vw; }
            .top h1 { font-size: 1.5rem; }
            .contentbox { max-width: 90%; margin: 30px auto 0; }
            .send button { font-size: 0.9rem; padding: 10px 0; }
        }
    </style>
</head>
<body>
<div class="header">
    <a href="about.html"><img src="logo.png" alt="タグスルのロゴ"></a>
</div>

<div class="top">
    <h1>ログイン</h1>
    <p>メールアドレスとパスワードを入力してログインしてください。</p>
</div>

<div class="contentbox">
    <?php if ($errors): ?>
        <div class="errors" style="color:red; margin-bottom:16px;">
            <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="post">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= h($csrf_token) ?>">
        <div class="content">
            <p>メールアドレス</p>
            <input type="email" name="email" class="textarea" required>
        </div>
        <div class="content">
            <p>パスワード</p>
            <input type="password" name="password" class="textarea" required>
        </div>
        <div class="send">
            <button type="submit" class="btn btn-dark">ログイン</button>
            <p>アカウントをお持ちでない方は <a href="register.php">登録</a></p>
        </div>
    </form>
</div>

<div class="footer">
    <p class="footertext">copyrights 2026 TAGUSURU All Rights Reserved.</p>
</div>
</body>
</html>

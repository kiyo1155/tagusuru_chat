<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

if (!function_exists('getDbConnection')) {
    function getDbConnection(): PDO {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}

if (!function_exists('normalizeEmail')) {
    /**
     * メールアドレスを正規化
     * - Gmail の場合、ドットを削除
     * - +以降を削除
     * - 小文字化
     */
    function normalizeEmail(string $email): string {
        $email = strtolower(trim($email));
        [$user, $domain] = explode('@', $email);

        if (in_array($domain, ['gmail.com', 'googlemail.com'])) {
            $user = str_replace('.', '', $user); // ドットを削除
            $user = preg_replace('/\+.*$/', '', $user); // +タグを削除
        }

        return $user . '@' . $domain;
    }
}

if (!function_exists('registerUser')) {
    function registerUser(string $company, string $name, string $email, string $password, string $role): bool {
        try {
            $pdo = getDbConnection();

            // 会社の存在確認
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE name = ?");
            $stmt->execute([$company]);
            $companyId = $stmt->fetchColumn();

            // 存在しなければ作成
            if (!$companyId) {
                $stmt = $pdo->prepare("INSERT INTO companies (name) VALUES (?)");
                $stmt->execute([$company]);
                $companyId = $pdo->lastInsertId();
            }

            // メールアドレスの重複チェック
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return false; // 既に存在する
            }

            // パスワードハッシュ化
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (company_id, name, email, password, role, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$companyId, $name, $email, $hashedPassword, $role]);

            // 認証用トークン生成
            // $token = bin2hex(random_bytes(32));

            // 仮登録
            // $stmt = $pdo->prepare(
            //     "INSERT INTO users (company_id, name, email, password, role, is_verified, verify_token, created_at) 
            //      VALUES (?, ?, ?, ?, ?, 0, ?, NOW())"
            // );
            // $stmt->execute([$companyId, $name, $email, $hashedPassword, $role, $token]);

            // 認証メール送信
            // $verifyUrl = "https://example.com/verify.php?token=" . urlencode($token);
            // $subject = "本登録のご案内";
            // $message = "{$name} 様\n\n以下のリンクをクリックして本登録を完了してください：\n\n{$verifyUrl}\n\n";
            // $headers = "From: no-reply@tagusuru.sakura.ne.jp\r\n";
            // $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            // mail($email, $subject, $message, $headers);

            return true;
        } catch (PDOException $e) {
            error_log("Register error: " . $e->getMessage());
            return false;
        }
    }
}

if (isLoggedIn()) {
    redirect('dashboard_requester.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken($_POST[CSRF_TOKEN_NAME]);

    $company = trim($_POST['company'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? '';

    if (!$company) $errors[] = '会社名を入力してください。';
    if (!$name) {
        $errors[] = '氏名を入力してください。';
    } elseif (preg_match('/\s/', $name)) {
        $errors[] = '氏名にスペースは使用できません。';
    }
    if (!$email) $errors[] = 'メールアドレスを入力してください。';
    if (!$password) $errors[] = 'パスワードを入力してください。';
    if (strlen($password) < 8) $errors[] = 'パスワードは8文字以上にしてください。';
    if ($password !== $password_confirm) $errors[] = 'パスワード確認が一致しません。';
    if (!$role) $errors[] = '区分を選択してください。';

    if (registerUser($company, $name, $email, $password, $role)) {
    // 登録成功したらログインページへ遷移
    redirect('login.php');
    exit;
} else {
    $errors[] = '登録に失敗しました。既に同じメールアドレスが存在する可能性があります。';
}
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規会員登録</title>
    <link rel="stylesheet" href="reset.css">
    <link rel="stylesheet" href="message.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .header { display: flex; align-items: center; padding: 10px 24px; background: #000; }
        .header img { width: 120px; height: 120px; object-fit: contain; }
        .top { background: #f9f9f9; padding: 60px 5vw; text-align: center; }
        .contentbox { max-width: 400px; margin: 40px auto 0; padding: 0 16px; }
        .content { display: flex; flex-direction: column; margin-bottom: 20px; }
        .content p { font-weight: 700; margin-bottom: 8px; text-align: center; }
        .textarea { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; }
        .textarea:focus { border-color: #000; outline: none; }
        .send { margin-bottom: 40px; text-align: center; }
        .send button { width: 100%; padding: 12px 0; border-radius: 8px; font-size: 1rem; font-weight: 700; }
        .send p { margin-top: 12px; font-size: 0.95rem; }
        .send a { text-decoration: underline; color: #000; }
        .footer { background: #000; color: #fff; text-align: center; padding: 20px 5vw; border-top: 1px solid #222; }
        .footertext { margin: 0; font-size: .9rem; color: #ccc; }
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
    <h1>新規会員登録</h1>
    <p>必要事項を入力して新規登録してください。</p>
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
            <p>会社名</p>
            <input type="text" name="company" class="textarea" required>
        </div>
        <div class="content">
            <p>氏名</p>
            <input type="text" name="name" class="textarea" required>
        </div>
        <div class="content">
            <p>メールアドレス</p>
            <input type="email" name="email" class="textarea" required>
        </div>
        <div class="content">
            <p>パスワード（8文字以上）</p>
            <input type="password" name="password" class="textarea" required>
        </div>
        <div class="content">
            <p>パスワード確認</p>
            <input type="password" name="password_confirm" class="textarea" required>
        </div>
        <div class="content">
        <p>区分</p>
            <select name="role" class="textarea" required>
            <option value="">選択してください</option>
            <option value="client">発注者（案件を依頼する）</option>
            <option value="vendor">受注者（案件を受ける）</option>
         </select>
        </div>
        <div class="send">
            <button type="submit" class="btn btn-dark">登録</button>
            <p>すでにアカウントをお持ちの方は <a href="login.php">ログイン</a></p>
        </div>
    </form>
</div>

<div class="footer">
    <p class="footertext">copyrights 2026 TAGUSURU All Rights Reserved.</p>
</div>
</body>
</html>

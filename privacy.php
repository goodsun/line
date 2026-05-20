<?php
// 事業者情報。実際の値に差し替える。
$OPERATOR_NAME = getenv('OPERATOR_NAME') ?: '（事業者名を記載）';
$CONTACT_EMAIL = getenv('CONTACT_EMAIL') ?: 'support@example.com';
$EFFECTIVE_DATE = getenv('PRIVACY_EFFECTIVE_DATE') ?: '2026-05-20';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>プライバシーポリシー</title>
  <style>
    body { font-family: -apple-system, "Hiragino Sans", sans-serif; padding: 24px; line-height: 1.8; max-width: 720px; margin: 0 auto; }
    h1 { font-size: 20px; }
    h2 { font-size: 16px; margin-top: 28px; border-left: 4px solid #06c755; padding-left: 8px; }
    ul { padding-left: 1.2em; }
    .meta { color: #888; font-size: 13px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
    th { background: #f5f5f5; white-space: nowrap; }
  </style>
</head>
<body>
  <h1>プライバシーポリシー</h1>
  <p class="meta">制定日: <?= htmlspecialchars($EFFECTIVE_DATE, ENT_QUOTES, 'UTF-8') ?></p>

  <p><?= htmlspecialchars($OPERATOR_NAME, ENT_QUOTES, 'UTF-8') ?>（以下「当方」）は、本アプリにおけるユーザーの個人情報を、以下の方針に基づき適切に取り扱います。</p>

  <h2>1. 取得する情報</h2>
  <ul>
    <li>メールアドレス</li>
    <li>LINEプロフィール情報（表示名、ユーザー識別子）</li>
  </ul>

  <h2>2. 利用目的</h2>
  <p>取得した情報は、以下の目的の範囲内で利用します。</p>
  <table>
    <tr><th>情報</th><th>利用目的</th></tr>
    <tr><td>メールアドレス</td><td>会員登録、本人確認、お問い合わせ対応のための連絡</td></tr>
    <tr><td>LINEプロフィール</td><td>会員の識別および本人確認</td></tr>
  </table>

  <h2>3. 第三者提供</h2>
  <p>当方は、法令に基づく場合を除き、ユーザーの同意なく個人情報を第三者に提供しません。</p>

  <h2>4. 安全管理</h2>
  <p>取得した情報は、漏えい・滅失・毀損を防止するため、適切な安全管理措置を講じて管理します。</p>

  <h2>5. 開示・訂正・利用停止・削除の請求</h2>
  <p>ユーザーは、自己の個人情報について、開示・訂正・利用停止・削除を請求できます。下記窓口までご連絡ください。</p>

  <h2>6. お問い合わせ窓口</h2>
  <p>個人情報の取り扱いに関するお問い合わせは、以下までご連絡ください。</p>
  <ul>
    <li>事業者: <?= htmlspecialchars($OPERATOR_NAME, ENT_QUOTES, 'UTF-8') ?></li>
    <li>メール: <?= htmlspecialchars($CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') ?></li>
  </ul>

  <h2>7. 本ポリシーの変更</h2>
  <p>当方は、必要に応じて本ポリシーを変更することがあります。変更後の内容は本ページに掲載した時点で効力を生じます。</p>
</body>
</html>

<?php
require __DIR__ . '/env.php';

$LIFF_ID = getenv('LIFF_ID') ?: '';
if ($LIFF_ID === '') {
    http_response_code(500);
    echo 'LIFF_ID is not configured. Set it in .env';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LINE ID表示</title>
  <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
  <style>
    body { font-family: -apple-system, "Hiragino Sans", sans-serif; padding: 24px; }
    .box { background: #f5f5f5; padding: 16px; border-radius: 8px; word-break: break-all; }
    .label { color: #888; font-size: 12px; margin-top: 12px; }
    .value { font-size: 16px; }
    .err { color: #c00; }
  </style>
</head>
<body>
  <h1>あなたのLINE ID</h1>
  <div class="box">
    <div class="label">userId</div>
    <div class="value" id="userId">読み込み中...</div>
    <div class="label">displayName</div>
    <div class="value" id="displayName">-</div>
  </div>

  <script>
    const LIFF_ID = <?= json_encode($LIFF_ID) ?>;

    async function main() {
      try {
        await liff.init({ liffId: LIFF_ID });

        if (!liff.isLoggedIn()) {
          liff.login();
          return;
        }

        const profile = await liff.getProfile();
        document.getElementById('userId').textContent = profile.userId;
        document.getElementById('displayName').textContent = profile.displayName;

        // PHPサーバーへ送信（受信確認）
        const res = await fetch('receive.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            userId: profile.userId,
            displayName: profile.displayName
          })
        });
        const data = await res.json();
        console.log('server response:', data);
      } catch (e) {
        const el = document.getElementById('userId');
        el.textContent = 'エラー: ' + e.message;
        el.classList.add('err');
      }
    }
    main();
  </script>
</body>
</html>

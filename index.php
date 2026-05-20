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
  <title>bonsoleil for line</title>
  <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
  <style>
    body { font-family: -apple-system, "Hiragino Sans", sans-serif; padding: 24px; line-height: 1.7; }
    .hidden { display: none; }
    .box { background: #f5f5f5; padding: 16px; border-radius: 8px; word-break: break-all; }
    .label { color: #888; font-size: 12px; margin-top: 12px; }
    .value { font-size: 16px; }
    .err { color: #c00; }
    .notice { border: 1px solid #ddd; border-radius: 8px; padding: 16px; }
    .notice h2 { font-size: 16px; margin: 0 0 12px; }
    .notice ul { padding-left: 1.2em; margin: 8px 0; }
    .agree-row { margin: 16px 0; }
    .btn {
      display: block; width: 100%; padding: 14px; border: 0; border-radius: 8px;
      background: #06c755; color: #fff; font-size: 16px; font-weight: bold; cursor: pointer;
    }
    .btn:disabled { background: #b7e3c6; cursor: not-allowed; }
    .btn-sub { background: #fff; color: #06c755; border: 1px solid #06c755; margin-top: 16px; }
    .avatar { width: 72px; height: 72px; border-radius: 50%; margin-top: 8px; display: block; }
    a { color: #06c755; }
  </style>
</head>
<body>
  <!-- 同意画面（メールアドレス取得権限の申請スクショ用） -->
  <section id="consent" class="hidden">
    <div class="notice">
      <h2>ご利用にあたっての同意</h2>
      <p>本アプリは、以下の情報を取得・利用します。内容をご確認のうえ、同意してログインしてください。</p>

      <p><strong>取得する情報</strong></p>
      <ul>
        <li>メールアドレス</li>
        <li>LINEプロフィール（表示名・ユーザー識別子）</li>
      </ul>

      <p><strong>利用目的</strong></p>
      <ul>
        <li>会員登録</li>
        <li>本人確認</li>
        <li>お問い合わせ対応</li>
      </ul>

      <p>取得した情報の取り扱いについては
        <a href="privacy.php" target="_blank" rel="noopener">プライバシーポリシー</a>
        をご確認ください。</p>

      <label class="agree-row">
        <input type="checkbox" id="agreeCheck">
        上記の内容に同意し、メールアドレスの取得に同意します
      </label>

      <button id="agreeBtn" class="btn" disabled>同意してログイン</button>
    </div>
  </section>

  <!-- 結果表示 -->
  <section id="result" class="hidden">
    <h1>あなたのLINE ID</h1>
    <div class="box">
      <div class="label">userId（サーバー検証済み）</div>
      <div class="value" id="userId">読み込み中...</div>
      <div class="label">displayName</div>
      <div class="value" id="displayName">-</div>
      <img id="pictureImg" class="avatar hidden" alt="プロフィール画像">
      <div class="label">email</div>
      <div class="value" id="email">-</div>
    </div>
    <button id="logoutBtn" class="btn btn-sub">ログアウト</button>
  </section>

  <script>
    const LIFF_ID = <?= json_encode($LIFF_ID) ?>;

    const $ = (id) => document.getElementById(id);
    const show = (id) => $(id).classList.remove('hidden');

    function showConsent() {
      show('consent');
      const check = $('agreeCheck');
      const btn = $('agreeBtn');
      check.addEventListener('change', () => { btn.disabled = !check.checked; });
      btn.addEventListener('click', () => {
        if (!liff.isLoggedIn()) {
          // 未ログイン: 同意済みフラグを残してログインへ。
          // ログイン後のリロードで結果表示に進む。
          sessionStorage.setItem('agreed', '1');
          liff.login();
        } else {
          // ログイン済み: 同意をもって結果表示に進む。
          $('consent').classList.add('hidden');
          showResult();
        }
      });
    }

    async function showResult() {
      show('result');
      $('logoutBtn').addEventListener('click', () => {
        liff.logout();
        location.reload();
      });
      try {
        // IDトークン（JWT）を取得してサーバーへ送る。
        // 値の信頼はサーバー側のverify結果のみに置く。
        const jwt = liff.getIDToken();
        if (!jwt) {
          throw new Error('IDトークンを取得できません（openidスコープを確認）');
        }

        const res = await fetch('receive.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ jwt })
        });
        const data = await res.json();
        console.log('server response:', data);

        if (data.status !== 'ok') {
          console.error('verify failed:', data);
          throw new Error('サーバー検証に失敗しました（' + (data.error || 'unknown') + '）');
        }

        $('userId').textContent = data.userId;
        $('displayName').textContent = data.displayName || '-';
        if (data.pictureUrl) {
          const img = $('pictureImg');
          img.src = data.pictureUrl;
          img.classList.remove('hidden');
        }
        $('email').textContent = data.email || 'emailスコープ未許可';
      } catch (e) {
        const el = $('userId');
        el.textContent = 'エラー: ' + e.message;
        el.classList.add('err');
      }
    }

    async function main() {
      await liff.init({ liffId: LIFF_ID });

      // ログイン直後（同意済み）のみ結果へ。それ以外は必ず同意画面を先に出す。
      // 自動再ログインされても、同意するまで情報は取得・表示しない。
      if (liff.isLoggedIn() && sessionStorage.getItem('agreed') === '1') {
        sessionStorage.removeItem('agreed');
        showResult();
      } else {
        showConsent();
      }
    }
    main();
  </script>
</body>
</html>

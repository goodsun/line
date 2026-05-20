# LINE ID表示ミニアプリ

LINEミニアプリ（LIFF）でログイン中のユーザーの `userId` を取得し、PHPサーバーで受け取って表示・記録する最小サンプル。

## 構成

```
line/
├── index.php     # LIFF初期化 → userId取得 → 画面表示 + サーバーへPOST
├── receive.php   # POSTされたuserIdを受け取り、users.log に追記
├── users.log     # 受信ログ（実行時に自動生成）
└── README.md
```

## 動作概要

```
[LINEアプリ] ──open──▶ [index.php (LIFF)] ──getProfile()──▶ userId取得
                              │
                              └── POST /receive.php ──▶ users.log に追記
                              │
                              └── 画面に userId / displayName を表示
```

## セットアップ

### 1. LINE Developers でチャネル作成

1. [LINE Developers Console](https://developers.line.biz/console/) にログイン
2. プロバイダ作成（既存があれば流用）
3. 「LINEログイン」チャネルを新規作成
   - チャネル種別: **LINEログイン**
   - アプリタイプ: **ウェブアプリ**

### 2. LIFFアプリを追加

作成したチャネルの「LIFF」タブで「追加」を押下し、以下を設定:

| 項目 | 値 |
|------|-----|
| LIFFアプリ名 | 任意 |
| サイズ | Full / Tall / Compact から選択 |
| エンドポイントURL | `https://<公開ホスト>/index.php` |
| Scope | `profile` （必須） |
| ボットリンク機能 | Off で可 |

発行された **LIFF ID** をメモする（例: `1234567890-AbCdEfGh`）。

### 3. 公開HTTPS URLを用意

LIFFはHTTPS必須。ローカル開発ならトンネルツールを使う。

**例: PHPビルトインサーバ + cloudflared**

```bash
# ターミナル1: PHPサーバー起動
cd /Users/goodsun/develop/line
LIFF_ID=1234567890-AbCdEfGh php -S localhost:8000

# ターミナル2: HTTPSトンネル
cloudflared tunnel --url http://localhost:8000
```

発行されたHTTPS URL（例: `https://random-words.trycloudflare.com`）を LIFFエンドポイントURLに登録する。

代替案: `ngrok http 8000` でも可。

### 4. LIFF ID を反映

以下のいずれかでLIFF IDをセットする:

**(a) 環境変数で渡す（推奨）**

```bash
LIFF_ID=1234567890-AbCdEfGh php -S localhost:8000
```

**(b) `index.php` を直接書き換え**

```php
$LIFF_ID = getenv('LIFF_ID') ?: '1234567890-AbCdEfGh'; // ←ここ
```

## 動作確認

1. LINEアプリのトークで `https://liff.line.me/<LIFF_ID>` を送って開く
   - またはLIFF管理画面でQRコードを取得しスマホで読み取る
2. 初回はLINEログイン画面 → プロフィール提供同意
3. アプリ画面に `userId` と `displayName` が表示される
4. サーバー側 `users.log` に同じ内容が追記される

```
[2026-05-20T12:34:56+09:00] U1234567890abcdef1234567890abcdef | 山田太郎
```

## 取得できる値

`liff.getProfile()` で取得できる主なフィールド:

| フィールド | 内容 |
|----------|------|
| `userId` | LINEユーザーの一意ID（`U` + 32桁hex）。**チャネルごとに異なる値** |
| `displayName` | 表示名 |
| `pictureUrl` | プロフィール画像URL（任意） |
| `statusMessage` | ステータスメッセージ（任意） |

## セキュリティ上の注意

このサンプルはクライアントから素の `userId` をPOSTしているため、本番運用には不十分。

**本番では IDトークン検証 を行うこと:**

1. クライアントで `liff.getIDToken()` を取得
2. サーバーへ送信
3. サーバーで LINE の `/oauth2/v2.1/verify` エンドポイントに対しIDトークンを検証
4. 検証済みペイロードの `sub` を信頼できる userId として利用

詳細: [LINE公式ドキュメント - IDトークンを検証する](https://developers.line.biz/ja/docs/line-login/verify-id-token/)

## ファイル権限

`receive.php` が `users.log` に書き込めるよう、Webサーバ実行ユーザに書き込み権限が必要:

```bash
chmod 755 /Users/goodsun/develop/line
touch /Users/goodsun/develop/line/users.log
chmod 664 /Users/goodsun/develop/line/users.log
```

## トラブルシュート

| 症状 | 原因・対処 |
|------|----------|
| `liff.init` でエラー | LIFF ID が間違っている / エンドポイントURLとアクセスURLが不一致 |
| ログイン後に空白 | ScopeがprofileになっていないとgetProfileが失敗 |
| ブラウザ直アクセスで動かない | LINE外ブラウザは `liff.isInClient()` で挙動が異なる。`liff.login()` で外部ブラウザログインに進む |
| `users.log` が作成されない | ディレクトリの書き込み権限不足 |
| HTTPS必須エラー | エンドポイントURLは必ずHTTPS。ローカルは cloudflared/ngrok 経由で |

## 参考リンク

- [LIFF Documentation](https://developers.line.biz/ja/docs/liff/)
- [LIFF SDK API Reference](https://developers.line.biz/ja/reference/liff/)
- [LINEミニアプリ概要](https://developers.line.biz/ja/docs/line-mini-app/)

# LINE ID表示ミニアプリ

LINE上で動作するミニアプリでログイン中のユーザーの `userId` を取得し、PHPサーバーで受け取って表示・記録する最小サンプル。

> **2026年現在の状況**: LIFF（LINE Front-end Framework）は **LINEミニアプリへブランド統合中**です。SDK・API（`liff.init` / `liff.getProfile` 等）は引き続き利用可能で互換性があります。本サンプルもLIFF SDKをそのまま使いますが、新規作成は「LINEミニアプリチャネル」推奨です。既存LIFFアプリも統合後も使い続けられます。詳細は [#LIFFとLINEミニアプリの関係](#liffとlineミニアプリの関係) を参照。

## 構成

```
line/
├── index.php       # 同意画面 → ログイン → IDトークン検証 → 結果表示
├── receive.php     # IDトークンをLINE verify APIで検証 → userId/email取得 → users.log に追記
├── privacy.php     # プライバシーポリシー（メール権限申請のスクショ用）
├── env.php         # .env ローダー
├── .env            # LIFF_ID等の設定（gitignore対象）
├── .env.example    # .env のテンプレ
├── users.log       # 受信ログ（実行時に自動生成）
└── README.md
```

## 動作概要

IDトークン（JWT）をサーバーに送り、LINEのverify APIで検証してから値を信頼する**本番版フロー**:

```
[LINEアプリ] ──open──▶ [index.php]
                          │
                          ├─ liff.getIDToken() でIDトークン取得
                          │
                          └─ POST /receive.php { idToken }
                                   │
                                   ├─ api.line.me/oauth2/v2.1/verify で検証
                                   │   （署名・aud・有効期限をLINE側が確認）
                                   │
                                   ├─ 検証済みペイロードから sub(userId)/name/email 取得
                                   ├─ users.log に追記
                                   └─ JSONで返却 ──▶ 画面に表示
```

クライアントの `getProfile()` / `getDecodedIDToken()` は署名検証されないため**信頼しない**。値の信頼はサーバーのverify結果のみに置く。

## セットアップ

### 1. LINE Developers でチャネル作成

1. [LINE Developers Console](https://developers.line.biz/console/) にログイン
2. プロバイダ作成（既存があれば流用）
3. チャネルを新規作成。**新規は「LINEミニアプリ」推奨**:
   - **(推奨) LINEミニアプリチャネル**: 長期運用前提。開発用/審査用/本番用の環境が同時に提供される。本番公開には審査が必要（開発用チャネルは未審査で動作確認可）
   - **(従来) LINEログインチャネル + LIFF**: 既存資産との互換性を取りたい場合。本サンプルのコードはどちらでも動作

### 2. ミニアプリ/LIFFアプリを追加

作成したチャネルの「LIFF」タブで「追加」を押下し、以下を設定:

| 項目 | 値 |
|------|-----|
| アプリ名 | 任意 |
| サイズ | Full / Tall / Compact から選択 |
| エンドポイントURL | `https://<公開ホスト>/index.php` |
| Scope | `profile` を**明示的に有効化**（必須）。LINEミニアプリではデフォルト `openid` のみのため、これを忘れると `liff.getProfile()` が失敗する |
| ボットリンク機能 | Off で可 |

発行された **LIFF ID** をメモする（例: `1234567890-AbCdEfGh`）。ブランド統合後も識別子は引き続き「LIFF ID」と呼ばれる。

### 3. 公開HTTPS URLを用意

LIFFはHTTPS必須。ローカル開発ならトンネルツールを使う。

**例: PHPビルトインサーバ + cloudflared**

```bash
# ターミナル1: PHPサーバー起動（.env から LIFF_ID を読む）
cd /Users/goodsun/develop/line
php -S localhost:8000

# ターミナル2: HTTPSトンネル
cloudflared tunnel --url http://localhost:8000
```

発行されたHTTPS URL（例: `https://random-words.trycloudflare.com`）を LIFFエンドポイントURLに登録する。

代替案: `ngrok http 8000` でも可。

### 4. LIFF ID を反映（.env）

`.env.example` をコピーして `.env` を作成し、発行された LIFF ID を記入する:

```bash
cp .env.example .env
```

```
# .env
LIFF_ID=1234567890-AbCdEfGh
LINE_CHANNEL_ID=1234567890
```

- `LIFF_ID`: ミニアプリ/LIFFの起動ID（`https://miniapp.line.me/<LIFF_ID>`）
- `LINE_CHANNEL_ID`: IDトークン検証に使うチャネルID。**LIFF IDの `-` より前の数字部分**と一致する

`index.php` / `receive.php` 起動時に `env.php` が `.env` を読み込み、`getenv()` で取得する。

**注意**: `.env` は `.gitignore` でコミット対象外。シェル環境変数が既にセットされている場合は `.env` の値は上書きされない。

## 動作確認

1. LINEアプリのトークで `https://liff.line.me/<LIFF_ID>` を送って開く
   - またはLIFF管理画面でQRコードを取得しスマホで読み取る
2. 初回はLINEログイン画面 → プロフィール（・メール）提供同意
3. アプリ画面に サーバー検証済みの `userId` / `displayName` / `email` が表示される
4. サーバー側 `users.log` に同じ内容が追記される

```
[2026-05-20T12:34:56+09:00] U1234567890abcdef1234567890abcdef | 山田太郎 | taro@example.com
```

（emailスコープ/権限が未設定なら末尾は `(no email)` になる）

## 取得できる値

IDトークンを `/oauth2/v2.1/verify` で検証して得られる主なクレーム:

| クレーム | 内容 | 取得条件 |
|---------|------|---------|
| `sub` | LINEユーザーの一意ID（`U` + 32桁hex）。**チャネルごとに異なる値** | `openid`（常時） |
| `name` | 表示名 | `profile` スコープ |
| `picture` | プロフィール画像URL | `profile` スコープ |
| `email` | メールアドレス | `email` スコープ **＋ メール権限申請の承認** |

> 電話番号・本名・住所・生年月日などは LINE からは**取得できない**。

## メールアドレスを取得する設定

`email` は `profile` スコープでは取れず、**メール権限申請の承認 ＋ `email` スコープ ＋ ユーザー同意**の3点が揃って初めて、verifyのレスポンスに含まれる。コード変更は不要で、コンソール設定だけで有効化できる。

### 手順1: メールアドレス取得権限を申請

1. [LINE Developers Console](https://developers.line.biz/console/) → 対象チャネルを開く
2. **「チャネル基本設定」タブ**を開く
3. 下部の **「メールアドレス取得権限」** セクション → **「申請」** ボタンを押す
4. 申請フォームで以下を提出:
   - メールアドレスの**利用目的**
   - メールを**収集・利用する画面のスクリーンショット**（利用目的の通知と同意取得が見えるもの）
5. LINE側の審査 → 承認されると「申請済み（承認）」になる

> 審査には数営業日かかる場合がある。承認前は `email` スコープを有効化してもメールは返らない。

#### 提出スクリーンショットについて

LINEが確認したいのは「**アプリ側がメール取得と利用目的をユーザーに通知し、同意を得ている箇所**」。本サンプルでは未ログイン時に表示される **同意画面（`index.php`）** がこれに該当する。

同意画面には以下が明示されている:

- 取得する情報: メールアドレス / LINEプロフィール
- 利用目的: 会員登録・本人確認・お問い合わせ対応
- [プライバシーポリシー](#)（`privacy.php`）へのリンク
- 「同意してログイン」ボタン（チェックボックス同意後に有効化）

**スクショの撮り方**: 一度LINE内でログアウト状態にして `https://miniapp.line.me/<LIFF_ID>` を開くと同意画面が出る。その画面をキャプチャして申請フォームにアップロードする。

`privacy.php` の事業者名・連絡先・制定日は `.env` で設定する:

```
OPERATOR_NAME=株式会社サンプル
CONTACT_EMAIL=support@example.com
PRIVACY_EFFECTIVE_DATE=2026-05-20
```

### 手順2: `email` スコープを有効化

- LINEミニアプリ: チャネル設定の **スコープ** で `email` にチェック（`profile` と併せて）
- LINE Login + LIFF: 同様にスコープ設定で `email` を追加

> LINEミニアプリはデフォルト `openid` のみ。`profile` / `email` は明示的に有効化する。

### 手順3: ユーザー同意

- 上記が揃った状態でユーザーがログインすると、同意画面に **「メールアドレス」** 項目が表示される
- ユーザーが許可した場合のみ、IDトークンに `email` クレームが含まれる

### 確認

3点が揃うと、画面の email 欄と `users.log` に実際のメールアドレスが入る:

```
[2026-05-20T12:34:56+09:00] U... | ちから | chikara@example.com
```

揃っていない場合は `email` が欠落し、画面は「取得不可」、ログ末尾は `(no email)` になる。

## セキュリティ上の注意（本サンプルの実装）

本サンプルは**本番版**として、以下を実装済み:

- クライアントは素のIDトークン（JWT）のみを送信
- サーバー（`receive.php`）が LINE の `/oauth2/v2.1/verify` に `id_token` + `client_id`(=`LINE_CHANNEL_ID`) を送り検証
- LINE側が署名・`aud`・有効期限を検証。200応答時のみ値を信頼
- 検証済みペイロードの `sub` / `name` / `email` のみを利用・記録

`liff.getProfile()` や `liff.getDecodedIDToken()` の戻り値はクライアント側で改ざん可能なため、サーバーの認可判断には使わない。

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
| `getProfile` で失敗 | `profile` スコープ未許可。特にLINEミニアプリチャネルはデフォルト `openid` のみ。コンソールで `profile` を明示的に有効化 |
| ブラウザ直アクセスで動かない | LINE外ブラウザは `liff.isInClient()` で挙動が異なる。`liff.login()` で外部ブラウザログインに進む |
| `users.log` が作成されない | ディレクトリの書き込み権限不足 |
| HTTPS必須エラー | エンドポイントURLは必ずHTTPS。ローカルは cloudflared/ngrok 経由で |

## LIFFとLINEミニアプリの関係

LINEは LIFF（LINE Front-end Framework）を **LINEミニアプリへブランド統合**する方針を発表しています（LY Corporation, 2025）。技術的な比較:

| 項目 | LIFF（従来） | LINEミニアプリ |
|------|-------------|----------------|
| 認証スコープ | 初回一括同意 | 随時取得、デフォルトは `openid` のみ |
| LINE外ブラウザ | 開ける | LINE内に自動誘導 |
| アクションボタン | 非表示可能 | 常時表示 |
| チャネル環境 | 開発/公開を切替 | 開発/審査/本番を同時保持 |
| 同一チャネル内の複数アプリ | 可 | 不可（個別チャネルが必要） |
| 本番公開時の審査 | 不要 | 必須 |
| SDK / API | `@line/liff` | `@line/liff` （同じSDKが使える） |

**本サンプルへの影響**: LIFF SDK の API（`liff.init`, `liff.getProfile`, `liff.getIDToken` 等）は両方式で互換のため、コード変更は不要。チャネル設定で `profile` スコープを許可しておけば、LINEミニアプリ・LIFFどちらでも動作する。

## 参考リンク

- [LIFF Documentation](https://developers.line.biz/ja/docs/liff/)
- [LIFF SDK API Reference](https://developers.line.biz/ja/reference/liff/)
- [LINEミニアプリ概要](https://developers.line.biz/ja/docs/line-mini-app/)
- [LIFFはLINEミニアプリに統合されます（LY Corporation Tech）](https://x.com/lycorptech_jp/status/1889538306335187248)

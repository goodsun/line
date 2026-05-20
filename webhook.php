<?php
// Messaging API Webhook 受信エンドポイント（自動応答ボット）。
// LINE Developers Console の Webhook URL にこのファイルのURLを設定する。
require __DIR__ . '/env.php';
require __DIR__ . '/line_api.php';

$secret = getenv('LINE_CHANNEL_SECRET') ?: '';
$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';

// 署名検証（チャネルシークレットでHMAC-SHA256 → Base64）。なりすまし防止のため必須。
if ($secret === '') {
    http_response_code(500);
    exit;
}
$expected = base64_encode(hash_hmac('sha256', $raw, $secret, true));
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    exit;
}

$data = json_decode($raw, true);
foreach (($data['events'] ?? []) as $event) {
    $type = $event['type'] ?? '';

    // テキストメッセージへの応答
    if ($type === 'message' && (($event['message']['type'] ?? '') === 'text')) {
        $reply = $event['replyToken'] ?? '';
        $text = $event['message']['text'] ?? '';
        if ($reply !== '') {
            if ($text === 'お問い合わせ') {
                // リッチメニュー左半分からの導線
                line_reply($reply, [line_text("お問い合わせありがとうございます。\nご用件をこのトークに送信してください。担当者が確認します。")]);
            } else {
                line_reply($reply, [line_text('「' . $text . '」を受け取りました')]);
            }
        }
    }

    // 友だち追加時のあいさつ
    if ($type === 'follow') {
        $reply = $event['replyToken'] ?? '';
        if ($reply !== '') {
            line_reply($reply, [line_text('友だち追加ありがとうございます！')]);
        }
    }
}

http_response_code(200);

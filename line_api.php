<?php
// LINE Messaging API クライアント。
// チャネルアクセストークン（.env の LINE_CHANNEL_ACCESS_TOKEN）を使う。

function line_bearer(): string
{
    return getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '';
}

// api.line.me へJSONをPOSTする低レベルヘルパー。
function line_api_request(string $endpoint, array $payload): array
{
    $bearer = line_bearer();
    if ($bearer === '') {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'LINE_CHANNEL_ACCESS_TOKEN not configured'];
    }
    $ch = curl_init('https://api.line.me' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $bearer,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [
        'ok'     => $status >= 200 && $status < 300,
        'status' => $status,
        'body'   => $body,
        'error'  => $err,
    ];
}

// テキストメッセージオブジェクトを作る。
function line_text(string $text): array
{
    return ['type' => 'text', 'text' => $text];
}

// 指定ユーザーにプッシュ送信。
function line_push(string $to, array $messages): array
{
    return line_api_request('/v2/bot/message/push', ['to' => $to, 'messages' => $messages]);
}

// 友だち全員にブロードキャスト。
function line_broadcast(array $messages): array
{
    return line_api_request('/v2/bot/message/broadcast', ['messages' => $messages]);
}

// Webhookのリプライトークンに応答。
function line_reply(string $reply, array $messages): array
{
    return line_api_request('/v2/bot/message/reply', ['replyToken' => $reply, 'messages' => $messages]);
}

// リッチメニューを作成。成功時は body に richMenuId が含まれる。
function line_create_richmenu(array $richMenu): array
{
    return line_api_request('/v2/bot/richmenu', $richMenu);
}

// リッチメニューに画像をアップロード（api-data.line.me、バイナリ）。
function line_upload_richmenu_image(string $richMenuId, string $imagePath): array
{
    $bearer = line_bearer();
    if ($bearer === '') {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'LINE_CHANNEL_ACCESS_TOKEN not configured'];
    }
    if (!is_readable($imagePath)) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'image not readable: ' . $imagePath];
    }
    $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $ctype = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/png';
    $ch = curl_init('https://api-data.line.me/v2/bot/richmenu/' . rawurlencode($richMenuId) . '/content');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => file_get_contents($imagePath),
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . $ctype,
            'Authorization: Bearer ' . $bearer,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $body, 'error' => $err];
}

// リッチメニューを全ユーザーのデフォルトに設定。
function line_set_default_richmenu(string $richMenuId): array
{
    $bearer = line_bearer();
    if ($bearer === '') {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'LINE_CHANNEL_ACCESS_TOKEN not configured'];
    }
    $ch = curl_init('https://api.line.me/v2/bot/user/all/richmenu/' . rawurlencode($richMenuId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $bearer,
            'Content-Length: 0',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $body, 'error' => $err];
}

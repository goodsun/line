<?php
require __DIR__ . '/env.php';
require __DIR__ . '/verify.php';
require __DIR__ . '/line_api.php';

header('Content-Type: application/json; charset=utf-8');

$channelId = getenv('LINE_CHANNEL_ID') ?: '';
if ($channelId === '') {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'LINE_CHANNEL_ID is not configured'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jwt = isset($input['jwt']) ? (string)$input['jwt'] : '';
$text = isset($input['text']) ? (string)$input['text'] : 'プッシュ通知のテストです。';

if ($jwt === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'idToken is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 送信先userIdは必ず検証済みトークンから取得する（クライアント申告のuserIdは信用しない）。
$result = verify_id_token($jwt, $channelId);
if (!$result['ok']) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'invalid id token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $result['payload']['sub'] ?? '';
$res = line_push($userId, [line_text($text)]);

if (!$res['ok']) {
    file_put_contents(
        __DIR__ . '/users.log',
        sprintf("[%s] PUSH_FAILED status=%d body=%s\n", date('c'), $res['status'], $res['body']),
        FILE_APPEND | LOCK_EX
    );
    http_response_code(502);
    echo json_encode(['status' => 'error', 'error' => 'push failed', 'line_status' => $res['status']], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);

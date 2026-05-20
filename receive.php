<?php
require __DIR__ . '/env.php';
require __DIR__ . '/verify.php';

header('Content-Type: application/json; charset=utf-8');

// IDトークンのaud(=チャネルID)と照合するため必須
$channelId = getenv('LINE_CHANNEL_ID') ?: '';
if ($channelId === '') {
    http_response_code(500);
    echo json_encode(['error' => 'LINE_CHANNEL_ID is not configured'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$jwt = isset($input['jwt']) ? (string)$input['jwt'] : '';

if ($jwt === '') {
    http_response_code(400);
    echo json_encode(['error' => 'idToken is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = verify_id_token($jwt, $channelId);

if (!$result['ok']) {
    // 失敗の詳細はサーバーログにのみ残し、クライアントには簡素なエラーを返す。
    $claims = jwt_payload_unverified($jwt);
    $diag = sprintf(
        "[%s] VERIFY_FAILED status=%d aud=%s configured=%s exp=%s line=%s\n",
        date('c'),
        $result['status'],
        $claims['aud'] ?? '?',
        $channelId,
        isset($claims['exp']) ? date('c', (int)$claims['exp']) : '?',
        $result['line'] !== null ? json_encode($result['line'], JSON_UNESCAPED_UNICODE) : 'null'
    );
    file_put_contents(__DIR__ . '/users.log', $diag, FILE_APPEND | LOCK_EX);

    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'invalid id token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 検証済みクレーム
$payload     = $result['payload'];
$userId      = $payload['sub'] ?? '';       // userId
$displayName = $payload['name'] ?? '';
$pictureUrl  = $payload['picture'] ?? null; // profileスコープ時に含まれる
$email       = $payload['email'] ?? null;   // emailスコープ + 権限承認時のみ含まれる

$logLine = sprintf(
    "[%s] %s | %s | %s\n",
    date('c'),
    $userId,
    $displayName,
    $email ?? '(no email)'
);
file_put_contents(__DIR__ . '/users.log', $logLine, FILE_APPEND | LOCK_EX);

echo json_encode([
    'status'      => 'ok',
    'userId'      => $userId,
    'displayName' => $displayName,
    'pictureUrl'  => $pictureUrl,
    'email'       => $email,
], JSON_UNESCAPED_UNICODE);

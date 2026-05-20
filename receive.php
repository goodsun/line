<?php
require __DIR__ . '/env.php';

header('Content-Type: application/json; charset=utf-8');

// JWTのペイロードを「検証せず」デコードする（診断・エラー表示用）。
// 認可判断には使わない。
function jwt_payload_unverified(string $jwt): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return null;
    }
    $b64 = strtr($parts[1], '-_', '+/');
    $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
    $json = base64_decode($b64, true);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

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

// LINEのverifyエンドポイントで署名・aud・有効期限を検証する。
// 200が返ればトークンは正当。検証済みペイロードのみを信頼する。
$ch = curl_init('https://api.line.me/oauth2/v2.1/verify');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'id_token' => $jwt,
        'client_id' => $channelId,
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 10,
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'verify request failed', 'detail' => $curlErr], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode($body, true);

if ($status !== 200 || !is_array($payload)) {
    // 失敗の詳細はサーバーログにのみ残し、クライアントには簡素なエラーを返す。
    $claims = jwt_payload_unverified($jwt);
    $diag = sprintf(
        "[%s] VERIFY_FAILED status=%d aud=%s configured=%s exp=%s line=%s\n",
        date('c'),
        $status,
        $claims['aud'] ?? '?',
        $channelId,
        isset($claims['exp']) ? date('c', (int)$claims['exp']) : '?',
        is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : 'null'
    );
    file_put_contents(__DIR__ . '/users.log', $diag, FILE_APPEND | LOCK_EX);

    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'invalid id token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 検証済みクレーム
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

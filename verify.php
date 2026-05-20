<?php
// LINE IDトークン検証の共通処理。receive.php / push.php から利用する。

// JWTのペイロードを「検証せず」デコードする（診断・エラー表示用）。認可判断には使わない。
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

// IDトークンをLINEのverifyエンドポイントで検証する。
// 戻り値: ['ok'=>bool, 'status'=>int, 'payload'=>?array(検証済みクレーム), 'line'=>?array(LINEのエラー)]
function verify_id_token(string $jwt, string $channelId): array
{
    $ch = curl_init('https://api.line.me/oauth2/v2.1/verify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'id_token'  => $jwt,
            'client_id' => $channelId,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'payload' => null, 'line' => null];
    }
    $payload = json_decode($body, true);
    if ($status !== 200 || !is_array($payload)) {
        return [
            'ok'      => false,
            'status'  => $status,
            'payload' => null,
            'line'    => is_array($payload) ? $payload : null,
        ];
    }
    return ['ok' => true, 'status' => 200, 'payload' => $payload, 'line' => null];
}

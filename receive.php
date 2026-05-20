<?php
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$userId = isset($input['userId']) ? (string)$input['userId'] : '';
$displayName = isset($input['displayName']) ? (string)$input['displayName'] : '';

if ($userId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'userId is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 受信ログ（必要に応じてDB保存などに置き換える）
$logLine = sprintf("[%s] %s | %s\n", date('c'), $userId, $displayName);
file_put_contents(__DIR__ . '/users.log', $logLine, FILE_APPEND | LOCK_EX);

echo json_encode([
    'status' => 'ok',
    'userId' => $userId,
    'displayName' => $displayName,
], JSON_UNESCAPED_UNICODE);

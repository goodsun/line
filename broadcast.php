<?php
// 友だち全員にテキストをブロードキャストするCLIスクリプト。
// 使い方: php broadcast.php "配信したいメッセージ"
require __DIR__ . '/env.php';
require __DIR__ . '/line_api.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit;
}

$message = $argv[1] ?? '';
if ($message === '') {
    fwrite(STDERR, "Usage: php broadcast.php \"メッセージ\"\n");
    exit(1);
}

$res = line_broadcast([line_text($message)]);
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
exit($res['ok'] ? 0 : 1);

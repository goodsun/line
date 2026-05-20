<?php
// リッチメニューを作成し、画像をアップロードして全ユーザーのデフォルトに設定するCLIスクリプト。
// 使い方: php richmenu.php path/to/image.png
//   画像サイズは 2500x843（compact）に合わせること。タップでミニアプリを開く。
require __DIR__ . '/env.php';
require __DIR__ . '/line_api.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit;
}

$imagePath = $argv[1] ?? '';
if ($imagePath === '') {
    fwrite(STDERR, "Usage: php richmenu.php path/to/image.png (2500x843)\n");
    exit(1);
}

$liffId = getenv('LIFF_ID') ?: '';
if ($liffId === '') {
    fwrite(STDERR, "LIFF_ID is not configured in .env\n");
    exit(1);
}

// 1. リッチメニューを作成（全面タップでミニアプリを開く）
$richMenu = [
    'size'        => ['width' => 2500, 'height' => 843],
    'selected'    => true,
    'name'        => 'main-menu',
    'chatBarText' => 'メニュー',
    'areas'       => [[
        'bounds' => ['x' => 0, 'y' => 0, 'width' => 2500, 'height' => 843],
        'action' => [
            'type'  => 'uri',
            'label' => 'アプリを開く',
            'uri'   => 'https://miniapp.line.me/' . $liffId,
        ],
    ]],
];

$created = line_create_richmenu($richMenu);
if (!$created['ok']) {
    fwrite(STDERR, "create failed: " . $created['body'] . "\n");
    exit(1);
}
$richMenuId = json_decode($created['body'], true)['richMenuId'] ?? '';
echo "created richMenuId=$richMenuId\n";

// 2. 画像をアップロード
$uploaded = line_upload_richmenu_image($richMenuId, $imagePath);
if (!$uploaded['ok']) {
    fwrite(STDERR, "image upload failed: " . $uploaded['body'] . " " . $uploaded['error'] . "\n");
    exit(1);
}
echo "image uploaded\n";

// 3. 全ユーザーのデフォルトに設定
$set = line_set_default_richmenu($richMenuId);
if (!$set['ok']) {
    fwrite(STDERR, "set default failed: " . $set['body'] . "\n");
    exit(1);
}
echo "set as default. done.\n";

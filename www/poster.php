<?php
/**
 * Sert les affiches stockées dans MONCINE_DATA/posters/ (URL /posters/123.jpg).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\PosterDelivery;
use Moncine\PosterStorage;

$id = (int) ($_GET['id'] ?? 0);
$ext = strtolower(trim((string) ($_GET['ext'] ?? '')));
if ($ext === 'jpeg') {
    $ext = 'jpg';
}

if ($id <= 0 || !preg_match('/^(jpg|png|webp)$/', $ext)) {
    http_response_code(400);
    exit;
}

$webPath = PosterStorage::WEB_PREFIX . '/' . $id . '.' . $ext;
$absolute = PosterStorage::filesystemPathFromWeb($webPath);
if ($absolute === null || !is_file($absolute)) {
    http_response_code(404);
    exit;
}

PosterDelivery::sendFile($absolute);

$stream = fopen($absolute, 'rb');
if ($stream === false) {
    http_response_code(500);
    exit;
}

while (!feof($stream)) {
    $chunk = fread($stream, 65536);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
}
fclose($stream);
exit;

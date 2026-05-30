<?php
/**
 * Téléchargement / lecture d’un fichier stocké (hors www/) — admin pour l’instant.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\LocalFilesystemObjectStorage;
use Moncine\MediaStorage;
use Moncine\StoredObjectDelivery;
use Moncine\StoredObjectRepository;

CatalogAdmin::denyUnlessAccess();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Identifiant invalide.';
    exit;
}

$repo = new StoredObjectRepository();
$row = $repo->findById($id);
if ($row === null) {
    http_response_code(404);
    echo 'Fichier introuvable.';
    exit;
}

$relativePath = (string) ($row['relative_path'] ?? '');
$absolute = MediaStorage::absolutePath($relativePath);
if ($absolute === '' || !MediaStorage::isInsideRoot($absolute) || !is_file($absolute)) {
    http_response_code(404);
    echo 'Fichier absent sur le disque.';
    exit;
}

$storage = new LocalFilesystemObjectStorage();
$stream = $storage->readStream($relativePath);
if ($stream === null) {
    http_response_code(500);
    echo 'Impossible d’ouvrir le fichier.';
    exit;
}

StoredObjectDelivery::sendFile($row, $absolute);

while (!feof($stream)) {
    $chunk = fread($stream, 65536);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
}
fclose($stream);
exit;

<?php
/**
 * Enregistre un fichier joint sur une fiche jeu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameAttachmentRepository;
use Moncine\MediaDomainGuards;
use Moncine\UploadLimits;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

MediaDomainGuards::ensureGameContext();

$bibId = (int) ($_POST['game_id'] ?? 0);
$returnUrl = View::gameUrl($bibId);

UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'attachment_file' => 'Fichier joint',
]);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameAttachmentRepository();

if (!UploadLimits::phpAllowsAttachmentUpload()) {
    header('Location: ' . $returnUrl . '&attachment_error=' . rawurlencode(strip_tags(UploadLimits::phpLimitsWarning())));
    exit;
}

if (!isset($_FILES['attachment_file']) || (int) ($_FILES['attachment_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header('Location: ' . $returnUrl . '&attachment_error=' . rawurlencode('Sélectionnez un fichier.'));
    exit;
}

$result = $repo->attachUploadedFile(
    $bibId,
    $userId,
    $foyerId,
    (string) $_FILES['attachment_file']['tmp_name'],
    (string) ($_FILES['attachment_file']['name'] ?? 'fichier'),
    (int) ($_FILES['attachment_file']['size'] ?? 0),
    (string) ($_POST['attachment_label'] ?? '')
);

if ($result !== true) {
    header('Location: ' . $returnUrl . '&attachment_error=' . rawurlencode((string) $result));
    exit;
}

header('Location: ' . $returnUrl . '&attachment=1');
exit;

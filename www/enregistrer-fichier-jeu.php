<?php
/**
 * Enregistre un ou plusieurs fichiers joints sur une fiche jeu (PDF manuel/soluce…).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
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

Csrf::rejectUnlessValid($_POST, $returnUrl);

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

$uploads = GameAttachmentRepository::normalizeUploadedFiles($_FILES['attachment_file'] ?? null);
if ($uploads === []) {
    header('Location: ' . $returnUrl . '&attachment_error=' . rawurlencode('Sélectionnez au moins un fichier.'));
    exit;
}

$kind = trim((string) ($_POST['attachment_kind'] ?? ''));
$label = trim((string) ($_POST['attachment_label'] ?? ''));
if ($label === '' && $kind !== '' && $kind !== 'Autre') {
    $label = $kind;
}

$saved = 0;
$errors = [];
foreach ($uploads as $upload) {
    $fileLabel = $label;
    // Plusieurs fichiers + un seul libellé : on précise le nom du fichier.
    if ($fileLabel !== '' && count($uploads) > 1) {
        $fileLabel = $fileLabel . ' — ' . (string) ($upload['name'] ?? 'fichier');
    }

    $result = $repo->attachUploadedFile(
        $bibId,
        $userId,
        $foyerId,
        (string) ($upload['tmp_name'] ?? ''),
        (string) ($upload['name'] ?? 'fichier'),
        (int) ($upload['size'] ?? 0),
        $fileLabel
    );
    if ($result === true) {
        $saved++;
    } else {
        $errors[] = (string) $result;
    }
}

if ($saved === 0) {
    $message = $errors[0] ?? 'Impossible d’enregistrer les fichiers.';
    header('Location: ' . $returnUrl . '&attachment_error=' . rawurlencode($message) . '#game-attachments');
    exit;
}

$query = 'attachment=1&attachment_count=' . $saved;
if ($errors !== []) {
    $query .= '&attachment_error=' . rawurlencode(
        $saved . ' fichier(s) enregistré(s), mais ' . count($errors) . ' échec(s) : ' . $errors[0]
    );
}

header('Location: ' . $returnUrl . '&' . $query . '#game-attachments');
exit;

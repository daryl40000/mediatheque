<?php
/**
 * Enregistre un ou plusieurs fichiers joints sur une fiche jeu catalogue
 * (PDF manuel/soluce…).
 *
 * Règles PDF partagés :
 * - utilisateur : peut ajouter un PDF seulement s’il n’y en a pas encore ;
 * - admin : peut ajouter PDF et autres formats (plusieurs fichiers) ;
 * - suppression : voir supprimer-fichier-jeu.php (admin uniquement).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\GameAttachmentRepository;
use Moncine\MediaDomainGuards;
use Moncine\UploadLimits;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

MediaDomainGuards::ensureGameContext();

if (!Auth::isLoggedIn()) {
    header('Location: /connexion.php');
    exit;
}

$oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
$returnUrl = View::oeuvreJeuUrl($oeuvreId);

Csrf::rejectUnlessValid($_POST, $returnUrl);

UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'attachment_file' => 'Fichier joint',
]);

$repo = new GameAttachmentRepository();
$isAdmin = CatalogAdmin::canAccess();

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

$hasPdfAlready = $repo->hasPdfForOeuvre($oeuvreId);
$saved = 0;
$errors = [];
foreach ($uploads as $upload) {
    $fileName = (string) ($upload['name'] ?? 'fichier');
    $isPdf = GameAttachmentRepository::looksLikePdf($fileName);

    if (!$isAdmin) {
        if (!$isPdf) {
            $errors[] = 'Seuls les administrateurs peuvent ajouter des fichiers autres que PDF.';
            continue;
        }
        if ($hasPdfAlready) {
            $errors[] = 'Un PDF est déjà présent. Seul un administrateur peut en ajouter un autre ou le remplacer.';
            continue;
        }
    }

    $fileLabel = $label;
    // Plusieurs fichiers + un seul libellé : on précise le nom du fichier.
    if ($fileLabel !== '' && count($uploads) > 1) {
        $fileLabel = $fileLabel . ' — ' . $fileName;
    }

    $result = $repo->attachUploadedFile(
        $oeuvreId,
        (string) ($upload['tmp_name'] ?? ''),
        $fileName,
        (int) ($upload['size'] ?? 0),
        $fileLabel
    );
    if ($result === true) {
        $saved++;
        if ($isPdf) {
            $hasPdfAlready = true;
        }
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

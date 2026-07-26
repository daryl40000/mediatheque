<?php
/**
 * Supprime un fichier joint d’une fiche jeu catalogue — réservé aux admins.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\GameAttachmentRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

MediaDomainGuards::ensureGameContext();
CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
$attachmentId = (int) ($_POST['attachment_id'] ?? 0);
$returnUrl = View::oeuvreJeuUrl($oeuvreId);

Csrf::rejectUnlessValid($_POST, $returnUrl);

$deleted = (new GameAttachmentRepository())->deleteById($attachmentId, $oeuvreId);
if (!$deleted) {
    header('Location: ' . $returnUrl . '&attachment_error=' . rawurlencode('Fichier introuvable ou accès refusé.'));
    exit;
}

header('Location: ' . $returnUrl . '&attachment_deleted=1');
exit;

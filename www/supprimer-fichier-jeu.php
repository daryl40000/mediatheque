<?php
/**
 * Supprime un fichier joint d’une fiche jeu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameAttachmentRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

MediaDomainGuards::ensureGameContext();

$bibId = (int) ($_POST['game_id'] ?? 0);
$attachmentId = (int) ($_POST['attachment_id'] ?? 0);
$returnUrl = View::gameUrl($bibId);

Csrf::rejectUnlessValid($_POST, $returnUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$deleted = (new GameAttachmentRepository())->deleteById($attachmentId, $bibId, $userId, $foyerId);
if (!$deleted) {
    header('Location: ' . $returnUrl . '&attachment_error=' . rawurlencode('Fichier introuvable ou accès refusé.'));
    exit;
}

header('Location: ' . $returnUrl . '&attachment_deleted=1');
exit;

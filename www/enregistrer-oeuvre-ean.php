<?php
/**
 * Ajout / suppression d’un code EAN catalogue sur une œuvre (admin).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\OeuvreEanRepository;
use Moncine\SupportPhysique;

CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
$redirect = '/oeuvre.php?id=' . $oeuvreId;
if ($oeuvreId <= 0) {
    header('Location: /catalogue.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, $redirect);

$eanRepo = new OeuvreEanRepository();
$action = (string) ($_POST['action'] ?? '');

if ($action === 'delete') {
    $eanId = (int) ($_POST['ean_id'] ?? 0);
    $result = $eanRepo->delete($eanId, $oeuvreId);
    $param = $result === true ? 'ean_deleted=1' : 'ean_error=' . rawurlencode((string) $result);
    header('Location: ' . $redirect . '&' . $param);
    exit;
}

$ean = (string) ($_POST['ean'] ?? '');
$support = SupportPhysique::normalize((string) ($_POST['support_physique'] ?? ''));
$label = trim((string) ($_POST['label'] ?? ''));

$result = $eanRepo->add($oeuvreId, $ean, $support, $label, 'manual');
$param = is_int($result) ? 'ean_added=1' : 'ean_error=' . rawurlencode((string) $result);
header('Location: ' . $redirect . '&' . $param);
exit;

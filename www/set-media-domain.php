<?php
/**
 * Change l’onglet média actif (session) puis redirige.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SafeRedirect;

$domain = MediaDomain::normalize((string) ($_GET['domain'] ?? $_POST['domain'] ?? ''));
MediaContext::set($domain);

$redirect = trim((string) ($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
if ($redirect === '' || !str_starts_with($redirect, '/')) {
    $redirect = '/films.php';
}

header('Location: ' . SafeRedirect::path($redirect));
exit;

<?php
/**
 * Change l’onglet média actif (session) puis redirige.
 *
 * POST recommandé (CSRF). GET accepté seulement avec jeton CSRF
 * (liens de navigation inter-onglets générés par l’app).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SafeRedirect;

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$params = $isPost ? $_POST : $_GET;
$csrfSubmitted = isset($params[Csrf::FIELD_NAME]) ? (string) $params[Csrf::FIELD_NAME] : null;

if (!Csrf::validate($csrfSubmitted)) {
    $fallback = SafeRedirect::path((string) ($params['redirect'] ?? '/films.php'));
    $separator = str_contains($fallback, '?') ? '&' : '?';
    header('Location: ' . $fallback . $separator . 'csrf_error=1');
    exit;
}

$domain = MediaDomain::normalize((string) ($params['domain'] ?? ''));
MediaContext::set($domain);

$redirect = trim((string) ($params['redirect'] ?? ''));
if ($redirect === '' || !str_starts_with($redirect, '/')) {
    $redirect = '/films.php';
}

header('Location: ' . SafeRedirect::path($redirect));
exit;

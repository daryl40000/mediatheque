<?php
/**
 * Mes envies musique — page « bientôt disponible » (phase M8).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\SafeRedirect;

if (MediaContext::current() !== MediaDomain::MUSIQUE) {
    MediaContext::set(MediaDomain::MUSIQUE);
    header('Location: ' . SafeRedirect::path('/musique-envies.php'));
    exit;
}

MediaDomainGuards::renderCollectionPageOrExit();

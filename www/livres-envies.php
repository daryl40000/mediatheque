<?php
/**
 * Mes envies livres — page « bientôt disponible » (phase M3).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;

if (MediaContext::current() !== MediaDomain::LIVRE) {
    header('Location: ' . MediaDomainGuards::mediaDomainSwitchUrl(MediaDomain::LIVRE, '/livres-envies.php'));
    exit;
}

MediaDomainGuards::renderCollectionPageOrExit();

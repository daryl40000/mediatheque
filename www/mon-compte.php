<?php
/**
 * Ancienne URL « Mon compte » → Paramètres.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

header('Location: /parametres.php', true, 301);
exit;

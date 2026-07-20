<?php
/**
 * Ressource absente (utilisateur, foyer, fiche catalogue…).
 *
 * Message destiné à l’utilisateur ou à une redirection ?error=.
 */

declare(strict_types=1);

namespace Moncine\Exception;

use RuntimeException;

final class NotFoundException extends RuntimeException
{
}

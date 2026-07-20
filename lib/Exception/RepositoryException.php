<?php
/**
 * Erreur technique repository (transaction, base de données…).
 *
 * Préférer ValidationException / NotFoundException pour les cas métier.
 */

declare(strict_types=1);

namespace Moncine\Exception;

use RuntimeException;

final class RepositoryException extends RuntimeException
{
}

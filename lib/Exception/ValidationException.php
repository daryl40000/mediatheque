<?php
/**
 * Erreur de validation affichable à l'utilisateur (formulaire, action de masse…).
 */

declare(strict_types=1);

namespace Moncine\Exception;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(
        string $message,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

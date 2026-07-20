<?php
/**
 * Validation de champs en chaîne (Phase F).
 *
 * Exemple :
 *   $email = Validator::of($brut)->trim()->email()->orThrow();
 */

declare(strict_types=1);

namespace Moncine\Validator;

use Moncine\Exception\ValidationException;

final class Validator
{
    private string $value;

    private ?string $error = null;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    /** Enlève les espaces en début / fin (si pas déjà en erreur). */
    public function trim(): self
    {
        if ($this->error !== null) {
            return $this;
        }
        $this->value = trim($this->value);

        return $this;
    }

    /** Met la valeur en minuscules (UTF-8). */
    public function lower(): self
    {
        if ($this->error !== null) {
            return $this;
        }
        $this->value = mb_strtolower($this->value, 'UTF-8');

        return $this;
    }

    public function required(string $message = 'Champ obligatoire.'): self
    {
        if ($this->error !== null) {
            return $this;
        }
        if ($this->value === '') {
            $this->error = $message;
        }

        return $this;
    }

    public function email(string $message = 'Adresse e-mail invalide.'): self
    {
        if ($this->error !== null) {
            return $this;
        }
        // Vide ou format incorrect → même message qu’avant (comportement inchangé).
        if ($this->value === '' || filter_var($this->value, FILTER_VALIDATE_EMAIL) === false) {
            $this->error = $message;
        }

        return $this;
    }

    /**
     * Longueur minimale (caractères UTF-8).
     * Pour les mots de passe (octets), préférer {@see self::byteLengthBetween()}.
     */
    public function minLength(int $min, string $message = ''): self
    {
        if ($this->error !== null) {
            return $this;
        }
        if (mb_strlen($this->value, 'UTF-8') < $min) {
            $this->error = $message !== ''
                ? $message
                : ('Trop court (minimum ' . $min . ' caractères).');
        }

        return $this;
    }

    /** Longueur maximale (caractères UTF-8). */
    public function maxLength(int $max, string $message = ''): self
    {
        if ($this->error !== null) {
            return $this;
        }
        if (mb_strlen($this->value, 'UTF-8') > $max) {
            $this->error = $message !== ''
                ? $message
                : ('Trop long (maximum ' . $max . ' caractères).');
        }

        return $this;
    }

    /**
     * Contrôle de longueur en octets (comme password_hash / strlen PHP).
     * Sert aux mots de passe pour rester aligné avec UtilisateurRepository::hashPassword().
     */
    public function byteLengthBetween(int $min, int $max, string $message): self
    {
        if ($this->error !== null) {
            return $this;
        }
        $len = strlen($this->value);
        if ($len < $min || $len > $max) {
            $this->error = $message;
        }

        return $this;
    }

    /**
     * Règle libre : si $ok est faux, enregistre le message.
     */
    public function assert(bool $ok, string $message): self
    {
        if ($this->error !== null) {
            return $this;
        }
        if (!$ok) {
            $this->error = $message;
        }

        return $this;
    }

    /**
     * Succès → valeur nettoyée ; échec → ValidationException.
     *
     * @throws ValidationException
     */
    public function orThrow(): string
    {
        if ($this->error !== null) {
            throw new ValidationException($this->error);
        }

        return $this->value;
    }

    /**
     * Mode « message texte » (sans exception) : true si OK, sinon le message.
     *
     * @return true|string
     */
    public function result(): bool|string
    {
        return $this->error ?? true;
    }

    /** Valeur courante (même si une erreur est déjà enregistrée). */
    public function value(): string
    {
        return $this->value;
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    public function errorMessage(): ?string
    {
        return $this->error;
    }
}

<?php
/**
 * Mode d’inscription publique (réglage administrateur, stocké en app_metadata).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class RegistrationSettings
{
    public const META_KEY = 'registration_mode';

    /** Inscription désactivée (comportement historique). */
    public const MODE_DISABLED = 'disabled';

    /** Inscription ouverte : après confirmation e-mail, compte actif immédiatement. */
    public const MODE_OPEN = 'open';

    /** Confirmation e-mail puis validation par un administrateur. */
    public const MODE_APPROVAL_REQUIRED = 'approval_required';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'app_metadata' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function isAvailable(): bool
    {
        return self::tableExists()
            && InscriptionRequestRepository::tableExists();
    }

    public function getMode(): string
    {
        if (!self::tableExists()) {
            return self::MODE_DISABLED;
        }

        $stmt = $this->db->prepare('SELECT value FROM app_metadata WHERE key = ? LIMIT 1');
        $stmt->execute([self::META_KEY]);
        $value = $stmt->fetchColumn();

        return self::normalize(is_string($value) ? $value : '');
    }

    public function setMode(string $mode): bool
    {
        $mode = self::normalize($mode);
        $stmt = $this->db->prepare(
            'INSERT INTO app_metadata (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );
        $stmt->execute([self::META_KEY, $mode]);

        return true;
    }

    public function isPublicRegistrationEnabled(): bool
    {
        return $this->getMode() !== self::MODE_DISABLED;
    }

    public function requiresAdminApproval(): bool
    {
        return $this->getMode() === self::MODE_APPROVAL_REQUIRED;
    }

    public static function normalize(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            self::MODE_OPEN, self::MODE_APPROVAL_REQUIRED => $mode,
            default => self::MODE_DISABLED,
        };
    }

    /** @return array<string, string> */
    public static function modeLabels(): array
    {
        return [
            self::MODE_DISABLED => 'Désactivée — seul l’administrateur crée les comptes',
            self::MODE_OPEN => 'Ouverte — confirmation par e-mail, puis connexion',
            self::MODE_APPROVAL_REQUIRED => 'Avec approbation — e-mail confirmé, puis validation admin',
        ];
    }
}

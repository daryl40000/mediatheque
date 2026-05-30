<?php
/**
 * Jetons de réinitialisation de mot de passe (stockés hachés en base).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;
use PDOException;

final class PasswordResetRepository
{
    /** Validité du lien (secondes). */
    public const TOKEN_TTL_SECONDS = 3600;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crée un jeton pour l’utilisateur. Retourne le jeton en clair (à mettre dans l’URL / e-mail).
     */
    public function createForUser(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $expires = gmdate('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        try {
            $this->db->prepare(
                'DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL'
            )->execute([$userId]);

            $this->db->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
            )->execute([$userId, $hash, $expires]);
        } catch (PDOException $e) {
            error_log('Moncine password_reset_tokens INSERT: ' . $e->getMessage());

            return null;
        }

        return $plain;
    }

    /** Utilisateur lié au jeton, ou null si invalide / expiré / déjà utilisé. */
    public function findUserIdByToken(string $plainToken): ?int
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return null;
        }

        $hash = hash('sha256', $plainToken);
        $stmt = $this->db->prepare(
            'SELECT user_id FROM password_reset_tokens
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > datetime(\'now\')
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $userId = $stmt->fetchColumn();

        return $userId !== false ? (int) $userId : null;
    }

    /**
     * Applique un nouveau mot de passe et invalide le jeton.
     *
     * @return true|string
     */
    public function resetPasswordWithToken(string $plainToken, string $newPassword): bool|string
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return 'Lien invalide ou expiré.';
        }

        $hash = hash('sha256', $plainToken);
        $newHash = UtilisateurRepository::hashPassword($newPassword);
        if ($newHash === null) {
            return UtilisateurRepository::passwordValidationMessage();
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT id, user_id FROM password_reset_tokens
                 WHERE token_hash = ? AND used_at IS NULL AND expires_at > datetime(\'now\')
                 LIMIT 1'
            );
            $stmt->execute([$hash]);
            $row = $stmt->fetch();
            if ($row === false) {
                $this->db->rollBack();

                return 'Lien invalide ou expiré.';
            }

            $tokenId = (int) $row['id'];
            $userId = (int) $row['user_id'];

            $this->db->prepare('UPDATE utilisateurs SET password_hash = ? WHERE id = ?')
                ->execute([$newHash, $userId]);
            $this->db->prepare(
                'UPDATE password_reset_tokens SET used_at = datetime(\'now\') WHERE id = ?'
            )->execute([$tokenId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Moncine password reset: ' . $e->getMessage());

            return 'Réinitialisation impossible. Réessayez plus tard.';
        }

        return true;
    }

    public function purgeExpired(): void
    {
        $this->db->exec(
            'DELETE FROM password_reset_tokens
             WHERE expires_at <= datetime(\'now\') OR used_at IS NOT NULL'
        );
    }
}

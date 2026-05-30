<?php
/**
 * Comptes utilisateurs (connexion, rôles).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class UtilisateurRepository
{
  /** Longueur minimale du mot de passe. */
    public const MIN_PASSWORD_LENGTH = 8;

  /** Limite pour éviter les abus (charge CPU du hachage). */
    public const MAX_PASSWORD_LENGTH = 128;

    private const PUBLIC_COLUMNS = 'id, nom, prenom, pseudo, ville, searchable, email, role, actif, foyer_id, last_login_at, created_at';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function countWithPassword(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE TRIM(password_hash) != '' AND actif = 1"
        )->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM utilisateurs WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM utilisateurs WHERE LOWER(TRIM(email)) = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null Ligne avec password_hash (connexion uniquement). */
    public function findByEmailForAuthentication(string $email): ?array
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, nom, email, password_hash, role, actif FROM utilisateurs
             WHERE LOWER(TRIM(email)) = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->db->query(
            'SELECT u.id, u.nom, u.prenom, u.pseudo, u.email, u.role, u.actif, u.foyer_id, u.last_login_at, u.created_at,
                    f.nom AS foyer_nom
             FROM utilisateurs u
             LEFT JOIN foyers f ON f.id = u.foyer_id
             ORDER BY u.role DESC, u.nom COLLATE FRENCH_NOCASE'
        )->fetchAll();
    }

    public function create(
        string $nom,
        string $email,
        string $plainPassword,
        string $role,
        int $foyerId = 0,
        string $prenom = '',
        string $pseudo = ''
    ): int|string {
        $nom = trim($nom);
        $prenom = trim($prenom);
        $pseudo = UserProfile::sanitizePseudo($pseudo);
        $email = mb_strtolower(trim($email), 'UTF-8');
        $role = UserRole::normalize($role);

        $identity = UserProfile::validateIdentityFields($nom, $prenom, $pseudo);
        if ($identity !== true) {
            return $identity;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse e-mail invalide.';
        }
        if ($this->findByEmail($email) !== null) {
            return 'Cette adresse e-mail est déjà utilisée.';
        }
        $hash = self::hashPassword($plainPassword);
        if ($hash === null) {
            return self::passwordValidationMessage();
        }

        if ($foyerId > 0 && (new FoyerRepository())->findById($foyerId) === null) {
            return 'Foyer introuvable.';
        }

        $this->db->prepare(
            'INSERT INTO utilisateurs (nom, prenom, pseudo, email, password_hash, role, actif, foyer_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?, datetime(\'now\'))'
        )->execute([
            $nom,
            $prenom,
            $pseudo,
            $email,
            $hash,
            $role,
            $foyerId > 0 ? $foyerId : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Crée un compte avec un hash déjà calculé (inscription confirmée par e-mail).
     *
     * @return int|string
     */
    public function createWithPasswordHash(
        string $nom,
        string $email,
        string $passwordHash,
        string $role = UserRole::USER,
        string $prenom = '',
        string $pseudo = ''
    ): int|string {
        $nom = trim($nom);
        $prenom = trim($prenom);
        $pseudo = UserProfile::sanitizePseudo($pseudo);
        $email = mb_strtolower(trim($email), 'UTF-8');
        $role = UserRole::normalize($role);
        $passwordHash = trim($passwordHash);

        $identity = UserProfile::validateIdentityFields($nom, $prenom, $pseudo);
        if ($identity !== true) {
            return $identity;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse e-mail invalide.';
        }
        if ($passwordHash === '') {
            return 'Mot de passe invalide.';
        }
        if ($this->findByEmail($email) !== null) {
            return 'Cette adresse e-mail est déjà utilisée.';
        }

        $this->db->prepare(
            'INSERT INTO utilisateurs (nom, prenom, pseudo, email, password_hash, role, actif, foyer_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NULL, datetime(\'now\'))'
        )->execute([
            $nom,
            $prenom,
            $pseudo,
            $email,
            $passwordHash,
            $role,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Premier compte administrateur (installation).
     *
     * @return int|string ID utilisateur ou message d’erreur
     */
    public function createFirstAdmin(string $nom, string $email, string $plainPassword): int|string
    {
        $this->db->beginTransaction();
        try {
            if ($this->countWithPassword() > 0) {
                $this->db->rollBack();

                return 'Un compte administrateur existe déjà. Utilisez la page de connexion.';
            }

            $result = $this->create($nom, $email, $plainPassword, UserRole::ADMIN);
            if (!is_int($result)) {
                $this->db->rollBack();

                return $result;
            }

            if (FoyerRepository::tableExists($this->db)) {
                (new FoyerRepository())->createDefaultForUser($result);
            }

            $this->db->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Moncine createFirstAdmin failed: ' . $e->getMessage());
            return 'Création du compte impossible.';
        }
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->prepare(
            'UPDATE utilisateurs SET last_login_at = datetime(\'now\') WHERE id = ?'
        )->execute([$id]);
    }

    /**
     * @return true|string
     */
    public function canSetActive(int $id, bool $active): bool|string
    {
        if ($active) {
            return true;
        }

        $user = $this->findById($id);
        if ($user === null) {
            return 'Compte introuvable.';
        }

        if (UserRole::isAdmin((string) ($user['role'] ?? '')) && $this->countAdmins() <= 1) {
            return 'Impossible de désactiver le dernier administrateur actif.';
        }

        return true;
    }

    public function setActive(int $id, bool $active): bool
    {
        if ($id <= 0 || $this->canSetActive($id, $active) !== true) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE utilisateurs SET actif = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);

        return $stmt->rowCount() > 0;
    }

    public function upgradePasswordHashIfNeeded(int $userId, string $currentHash, string $plainPassword): void
    {
        if ($userId <= 0 || $currentHash === '' || $plainPassword === '') {
            return;
        }
        if (!password_needs_rehash($currentHash, PASSWORD_DEFAULT)) {
            return;
        }
        $newHash = self::hashPassword($plainPassword);
        if ($newHash === null) {
            return;
        }
        $this->db->prepare('UPDATE utilisateurs SET password_hash = ? WHERE id = ?')
            ->execute([$newHash, $userId]);
    }

    public function countAdmins(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin' AND actif = 1"
        )->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveAdmins(): array
    {
        $stmt = $this->db->query(
            'SELECT ' . self::PUBLIC_COLUMNS . "
             FROM utilisateurs
             WHERE role = 'admin' AND actif = 1
             ORDER BY nom COLLATE FRENCH_NOCASE"
        );

        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function countLibraryEntries(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque WHERE user_id = ? AND statut = ?'
        );
        $stmt->execute([$userId, LibraryStatut::WISHLIST]);
        $wishlist = (int) $stmt->fetchColumn();

        if ($foyerId <= 0) {
            return $wishlist;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque WHERE foyer_id = ? AND statut = ?'
        );
        $stmt->execute([$foyerId, LibraryStatut::COLLECTION]);

        return $wishlist + (int) $stmt->fetchColumn();
    }

    /**
     * Supprime un compte et toute sa bibliothèque (films, envies, historique de vision).
     *
     * @return true|string
     */
    public function delete(int $id): bool|string
    {
        if ($id <= 0) {
            return 'Compte invalide.';
        }

        $user = $this->findById($id);
        if ($user === null) {
            return 'Compte introuvable.';
        }

        if (UserRole::isAdmin((string) ($user['role'] ?? '')) && $this->countAdmins() <= 1) {
            return 'Impossible de supprimer le dernier administrateur actif.';
        }

        $this->db->beginTransaction();
        try {
            $this->prepareUserDeletion($id);
            $stmt = $this->db->prepare('DELETE FROM utilisateurs WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() < 1) {
                $this->db->rollBack();

                return 'Compte introuvable.';
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Moncine delete user #' . $id . ': ' . $e->getMessage());

            return 'Suppression impossible. Réessayez ou consultez les logs du serveur.';
        }

        return true;
    }

    /**
     * Suppression du compte par l’utilisateur connecté (mot de passe requis).
     * Les comptes administrateur ne peuvent pas être supprimés ainsi.
     *
     * @return true|string
     */
    public function deleteOwnAccount(int $userId, string $currentPassword): bool|string
    {
        if ($userId <= 0) {
            return 'Compte invalide.';
        }

        $user = $this->findByIdForAuthentication($userId);
        if ($user === null) {
            return 'Compte introuvable.';
        }

        if (UserRole::isAdmin((string) ($user['role'] ?? ''))) {
            return 'Les comptes administrateur ne peuvent pas être supprimés depuis cette page. Demandez à un autre administrateur si besoin.';
        }

        if (!self::verifyPassword($user, $currentPassword)) {
            return 'Mot de passe incorrect.';
        }

        return $this->delete($userId);
    }

    /**
     * Nettoie les données liées avant DELETE utilisateurs (contraintes FK SQLite).
     */
    private function prepareUserDeletion(int $userId): void
    {
        $user = $this->findById($userId);
        if ($user === null) {
            return;
        }

        $this->removeSoloFoyersForUser($userId);

        if ($this->tableExists('email_change_requests')) {
            (new EmailChangeRepository())->deleteForUser($userId);
        }

        if ($this->tableExists('share_links')) {
            $this->db->prepare(
                "UPDATE share_links SET revoked_at = datetime('now')
                 WHERE user_id = ? AND revoked_at IS NULL"
            )->execute([$userId]);
        }

        $this->db->prepare('UPDATE utilisateurs SET foyer_id = NULL WHERE id = ?')->execute([$userId]);

        if ($this->tableExists('catalog_admin_audit')) {
            $this->db->prepare('DELETE FROM catalog_admin_audit WHERE user_id = ?')->execute([$userId]);
        }

        $this->db->prepare('DELETE FROM historique WHERE user_id = ?')->execute([$userId]);
        $this->reassignOrRemoveUserBibliotheque($userId);

        $email = mb_strtolower(trim((string) ($user['email'] ?? '')), 'UTF-8');
        if ($email !== '' && $this->tableExists('inscription_requests')) {
            $this->db->prepare(
                'DELETE FROM inscription_requests WHERE LOWER(TRIM(email)) = ?'
            )->execute([$email]);
        }

        if ($this->tableExists('group_members')) {
            $this->db->prepare('UPDATE group_members SET invited_by = NULL WHERE invited_by = ?')
                ->execute([$userId]);
        }

        if ($this->tableExists('foyers')) {
            $this->db->prepare('UPDATE foyers SET created_by_user_id = NULL WHERE created_by_user_id = ?')
                ->execute([$userId]);
        }

        $this->db->prepare(
            'DELETE FROM historique WHERE film_id IN (SELECT id FROM bibliotheque WHERE user_id = ?)'
        )->execute([$userId]);
        $this->db->prepare('DELETE FROM bibliotheque WHERE user_id = ?')->execute([$userId]);
    }

    private function reassignOrRemoveUserBibliotheque(int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id, foyer_id, statut FROM bibliotheque WHERE user_id = ?');
        $stmt->execute([$userId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bibId = (int) ($row['id'] ?? 0);
            if ($bibId <= 0) {
                continue;
            }

            $statut = (string) ($row['statut'] ?? '');
            $foyerId = (int) ($row['foyer_id'] ?? 0);

            if ($statut === LibraryStatut::COLLECTION && $foyerId > 0) {
                $successorId = $this->findGroupSuccessorUserId($foyerId, $userId);
                if ($successorId > 0) {
                    $this->db->prepare('UPDATE bibliotheque SET user_id = ? WHERE id = ?')
                        ->execute([$successorId, $bibId]);

                    continue;
                }
            }

            $this->db->prepare('DELETE FROM historique WHERE film_id = ?')->execute([$bibId]);
            $this->db->prepare('DELETE FROM bibliotheque WHERE id = ?')->execute([$bibId]);
        }
    }

    /** Supprime les foyers dont l’utilisateur est le seul membre (évite les foyers orphelins). */
    private function removeSoloFoyersForUser(int $userId): void
    {
        if (!$this->tableExists('group_members') || !$this->tableExists('foyers')) {
            return;
        }

        $stmt = $this->db->prepare('SELECT foyer_id FROM group_members WHERE user_id = ?');
        $stmt->execute([$userId]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $foyerId) {
            $foyerId = (int) $foyerId;
            if ($foyerId <= 0) {
                continue;
            }

            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM group_members WHERE foyer_id = ?');
            $countStmt->execute([$foyerId]);
            if ((int) $countStmt->fetchColumn() !== 1) {
                continue;
            }

            $this->db->prepare(
                'DELETE FROM historique WHERE film_id IN (SELECT id FROM bibliotheque WHERE foyer_id = ?)'
            )->execute([$foyerId]);
            $this->db->prepare('DELETE FROM bibliotheque WHERE foyer_id = ?')->execute([$foyerId]);

            if ($this->tableExists('group_invitations')) {
                $this->db->prepare('DELETE FROM group_invitations WHERE foyer_id = ?')->execute([$foyerId]);
            }

            $this->db->prepare('DELETE FROM group_members WHERE foyer_id = ?')->execute([$foyerId]);
            $this->db->prepare('DELETE FROM foyers WHERE id = ?')->execute([$foyerId]);
        }
    }

    private function findGroupSuccessorUserId(int $foyerId, int $excludeUserId): int
    {
        if ($foyerId <= 0 || !$this->tableExists('group_members')) {
            return 0;
        }

        $stmt = $this->db->prepare(
            "SELECT user_id FROM group_members
             WHERE foyer_id = ? AND user_id != ?
             ORDER BY CASE role WHEN 'founder' THEN 0 ELSE 1 END, user_id ASC
             LIMIT 1"
        );
        $stmt->execute([$foyerId, $excludeUserId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (int) $value : 0;
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1"
        );
        $stmt->execute([$tableName]);

        return $stmt->fetchColumn() !== false;
    }

    public static function hashPassword(string $plain): ?string
    {
        $len = strlen($plain);
        if ($len < self::MIN_PASSWORD_LENGTH || $len > self::MAX_PASSWORD_LENGTH) {
            return null;
        }

        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function passwordValidationMessage(): string
    {
        return 'Mot de passe invalide (' . self::MIN_PASSWORD_LENGTH . ' à ' . self::MAX_PASSWORD_LENGTH . ' caractères).';
    }

    public static function verifyPassword(array $user, string $plain): bool
    {
        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || $plain === '') {
            return false;
        }

        return password_verify($plain, $hash);
    }

    /** @return array<string, mixed>|null */
    public function findByIdForAuthentication(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, nom, email, password_hash, role, actif FROM utilisateurs WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return true|string
     */
    /**
     * Utilisateurs actifs acceptant d’apparaître dans la recherche (pseudo et/ou ville).
     *
     * @return list<array<string, mixed>>
     */
    public function searchDiscoverableUsers(string $pseudoQuery, string $villeQuery, int $excludeUserId): array
    {
        $pseudoQuery = UserProfile::sanitizePseudo($pseudoQuery);
        $villeQuery = UserProfile::sanitizeVille($villeQuery);

        if ($pseudoQuery === '' && $villeQuery === '') {
            return [];
        }

        $conditions = ['actif = 1', 'searchable = 1'];
        $params = [];

        if ($excludeUserId > 0) {
            $conditions[] = 'id != ?';
            $params[] = $excludeUserId;
        }

        if ($pseudoQuery !== '') {
            $conditions[] = "LOWER(TRIM(pseudo)) LIKE ? ESCAPE '\\'";
            $params[] = LikePattern::containsFragment(mb_strtolower($pseudoQuery, 'UTF-8'));
        }

        if ($villeQuery !== '') {
            $conditions[] = "LOWER(TRIM(ville)) LIKE ? ESCAPE '\\'";
            $params[] = LikePattern::containsFragment(mb_strtolower($villeQuery, 'UTF-8'));
        }

        if ($excludeUserId > 0 && FriendshipRepository::isAvailable()) {
            $conditions[] = 'NOT EXISTS (
                SELECT 1 FROM friendships fb
                WHERE fb.status = ?
                  AND (
                    (fb.requester_id = ? AND fb.addressee_id = utilisateurs.id)
                    OR (fb.requester_id = utilisateurs.id AND fb.addressee_id = ?)
                  )
            )';
            $params[] = FriendshipRepository::STATUS_BLOCKED;
            $params[] = $excludeUserId;
            $params[] = $excludeUserId;
        }

        $sql = 'SELECT id, nom, prenom, pseudo, ville FROM utilisateurs WHERE '
            . implode(' AND ', $conditions)
            . ' ORDER BY pseudo COLLATE FRENCH_NOCASE, nom COLLATE FRENCH_NOCASE LIMIT 50';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function updateProfile(
        int $id,
        string $nom,
        string $prenom,
        string $email,
        string $pseudo = '',
        string $ville = '',
        bool $searchable = true,
        string $currentPassword = ''
    ): bool|string {
        $user = $this->findById($id);
        if ($user === null) {
            return 'Compte introuvable.';
        }

        $newEmail = mb_strtolower(trim($email), 'UTF-8');
        $oldEmail = mb_strtolower(trim((string) ($user['email'] ?? '')), 'UTF-8');

        if ($newEmail !== $oldEmail) {
            return (new EmailChangeService())->requestChange(
                $id,
                $currentPassword,
                $newEmail,
                $nom,
                $prenom,
                $pseudo,
                $ville,
                $searchable
            );
        }

        return $this->updateProfileWithoutEmail($id, $nom, $prenom, $pseudo, $ville, $searchable);
    }

    /**
     * @return true|string
     */
    public function updateProfileWithoutEmail(
        int $id,
        string $nom,
        string $prenom,
        string $pseudo = '',
        string $ville = '',
        bool $searchable = true
    ): bool|string {
        $nom = trim($nom);
        $prenom = trim($prenom);
        $pseudo = UserProfile::sanitizePseudo($pseudo);
        $ville = UserProfile::sanitizeVille($ville);

        if ($id <= 0) {
            return 'Compte invalide.';
        }

        $identity = UserProfile::validateIdentityFields($nom, $prenom, $pseudo);
        if ($identity !== true) {
            return $identity;
        }

        $stmt = $this->db->prepare(
            'UPDATE utilisateurs SET nom = ?, prenom = ?, pseudo = ?, ville = ?, searchable = ? WHERE id = ?'
        );
        $stmt->execute([$nom, $prenom, $pseudo, $ville, $searchable ? 1 : 0, $id]);

        return $stmt->rowCount() > 0 ? true : 'Compte introuvable.';
    }

    /**
     * @return true|string
     */
    public function applyEmailChange(int $id, string $email): bool|string
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($id <= 0) {
            return 'Compte invalide.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse e-mail invalide.';
        }

        $existing = $this->findByEmail($email);
        if ($existing !== null && (int) ($existing['id'] ?? 0) !== $id) {
            return 'Cette adresse e-mail est déjà utilisée.';
        }

        $stmt = $this->db->prepare('UPDATE utilisateurs SET email = ? WHERE id = ?');
        $stmt->execute([$email, $id]);

        return $stmt->rowCount() > 0 ? true : 'Compte introuvable.';
    }

    /**
     * @return true|string
     */
    public function changePassword(int $id, string $currentPassword, string $newPassword): bool|string
    {
        $user = $this->findByIdForAuthentication($id);
        if ($user === null) {
            return 'Compte introuvable.';
        }
        if (!self::verifyPassword($user, $currentPassword)) {
            return 'Mot de passe actuel incorrect.';
        }

        $hash = self::hashPassword($newPassword);
        if ($hash === null) {
            return self::passwordValidationMessage();
        }

        $this->db->prepare('UPDATE utilisateurs SET password_hash = ? WHERE id = ?')
            ->execute([$hash, $id]);

        return true;
    }

    /**
     * Mot de passe provisoire (affiché une seule fois par l’administrateur).
     *
     * @return array{password: string}|string
     */
    public function adminSetTemporaryPassword(int $id): array|string
    {
        if ($id <= 0) {
            return 'Compte invalide.';
        }
        if ($this->findById($id) === null) {
            return 'Compte introuvable.';
        }

        $plain = self::generateTemporaryPassword();
        $hash = self::hashPassword($plain);
        if ($hash === null) {
            return 'Génération du mot de passe impossible.';
        }

        $this->db->prepare('UPDATE utilisateurs SET password_hash = ? WHERE id = ?')
            ->execute([$hash, $id]);

        return ['password' => $plain];
    }

    public static function generateTemporaryPassword(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $len = strlen($chars);
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $chars[random_int(0, $len - 1)];
        }

        return $out;
    }

    /**
     * Demande de réinitialisation par e-mail (message toujours neutre côté appelant).
     */
    public function requestPasswordResetEmail(string $email): void
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '' || PasswordResetThrottle::isBlocked($email)) {
            PasswordResetThrottle::recordAttempt($email);

            return;
        }

        PasswordResetThrottle::recordAttempt($email);

        $user = $this->findByEmailForAuthentication($email);
        if ($user === null || (int) ($user['actif'] ?? 0) !== 1) {
            return;
        }

        $tokenRepo = new PasswordResetRepository();
        $tokenRepo->purgeExpired();
        $plain = $tokenRepo->createForUser((int) $user['id']);
        if ($plain === null) {
            return;
        }

        $url = AppUrl::path('/reinitialiser-mot-de-passe.php?token=' . rawurlencode($plain));
        MailService::sendPasswordReset(
            $email,
            (string) ($user['nom'] ?? ''),
            $url
        );
    }
}

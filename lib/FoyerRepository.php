<?php
/**
 * Foyers : collection partagée entre plusieurs comptes (famille, coloc…).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FoyerRepository
{
    public const DEFAULT_NAME = 'Notre foyer';

    /** Nom du foyer créé automatiquement pour un compte seul. */
    public const PERSONAL_DEFAULT_NAME = 'Mon foyer';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(PDO $db): bool
    {
        $stmt = $db->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'foyers' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $cols = self::isExtendedSchema() ? 'id, nom, kind, created_by_user_id, created_at' : 'id, nom, created_at';
        $stmt = $this->db->prepare(
            'SELECT ' . $cols . ' FROM foyers WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->db->query(
            'SELECT f.id, f.nom, f.created_at,
                    (SELECT COUNT(*) FROM utilisateurs u WHERE u.foyer_id = f.id) AS member_count,
                    (SELECT COUNT(*) FROM bibliotheque b
                     WHERE b.foyer_id = f.id AND b.statut = \'collection\') AS collection_count
             FROM foyers f
             ORDER BY f.nom COLLATE FRENCH_NOCASE, f.id ASC'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listMembers(int $foyerId): array
    {
        if ($foyerId <= 0) {
            return [];
        }
        if (FamilyGroupService::isAvailable()) {
            return (new FamilyGroupService())->listMembers($foyerId);
        }
        $stmt = $this->db->prepare(
            'SELECT id, nom, email, role, actif, last_login_at, created_at
             FROM utilisateurs
             WHERE foyer_id = ?
             ORDER BY nom COLLATE FRENCH_NOCASE'
        );
        $stmt->execute([$foyerId]);

        return $stmt->fetchAll();
    }

    public function countMembers(int $foyerId): int
    {
        if ($foyerId <= 0) {
            return 0;
        }
        if (FamilyGroupService::isAvailable()) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM group_members WHERE foyer_id = ?');
            $stmt->execute([$foyerId]);

            return (int) $stmt->fetchColumn();
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE foyer_id = ?');
        $stmt->execute([$foyerId]);

        return (int) $stmt->fetchColumn();
    }

    private static function isExtendedSchema(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM pragma_table_info('foyers') WHERE name = 'kind' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    /** @return int|string */
    public function create(string $nom): int|string
    {
        $nom = trim($nom);
        if ($nom === '') {
            return 'Le nom du foyer est obligatoire.';
        }

        $this->db->prepare('INSERT INTO foyers (nom, created_at) VALUES (?, datetime(\'now\'))')
            ->execute([$nom]);

        return (int) $this->db->lastInsertId();
    }

    /** @return true|string */
    public function update(int $id, string $nom): bool|string
    {
        $nom = trim($nom);
        if ($id <= 0) {
            return 'Foyer invalide.';
        }
        if ($nom === '') {
            return 'Le nom du foyer est obligatoire.';
        }
        if ($this->findById($id) === null) {
            return 'Foyer introuvable.';
        }

        $this->db->prepare('UPDATE foyers SET nom = ? WHERE id = ?')->execute([$nom, $id]);

        return true;
    }

    /** @return true|string */
    public function delete(int $id): bool|string
    {
        if ($id <= 0) {
            return 'Foyer invalide.';
        }
        if ($this->findById($id) === null) {
            return 'Foyer introuvable.';
        }
        if ($this->countMembers($id) > 0) {
            return 'Impossible de supprimer un foyer qui contient encore des membres.';
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque WHERE foyer_id = ? AND statut = ?'
        );
        $stmt->execute([$id, LibraryStatut::COLLECTION]);
        if ((int) $stmt->fetchColumn() > 0) {
            return 'Impossible de supprimer un foyer qui possède encore des films en collection.';
        }

        $this->db->prepare('DELETE FROM foyers WHERE id = ?')->execute([$id]);

        return true;
    }

    /** @return true|string */
    public function assignUser(int $userId, int $foyerId): bool|string
    {
        if ($userId <= 0) {
            return 'Compte invalide.';
        }
        if ($foyerId <= 0) {
            return 'Foyer invalide.';
        }
        if ($this->findById($foyerId) === null) {
            return 'Foyer introuvable.';
        }

        $userRepo = new UtilisateurRepository();
        if ($userRepo->findById($userId) === null) {
            return 'Compte introuvable.';
        }

        $this->db->prepare('UPDATE utilisateurs SET foyer_id = ? WHERE id = ?')
            ->execute([$foyerId, $userId]);

        if (FamilyGroupService::isAvailable()) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM group_members WHERE foyer_id = ? AND user_id = ? LIMIT 1'
            );
            $stmt->execute([$foyerId, $userId]);
            if (!$stmt->fetchColumn()) {
                $this->db->prepare(
                    'INSERT INTO group_members (foyer_id, user_id, role, joined_at)
                     VALUES (?, ?, ?, datetime(\'now\'))'
                )->execute([$foyerId, $userId, FamilyGroupService::ROLE_MEMBER]);
            }
        }

        return true;
    }

    public function createDefaultForUser(int $userId, string $nom = self::DEFAULT_NAME): int
    {
        if (FamilyGroupService::isAvailable()) {
            $result = (new FamilyGroupService())->createGroup($userId, $nom);
            if (!is_int($result)) {
                throw new \RuntimeException((string) $result);
            }

            return $result;
        }

        $foyerId = $this->create($nom);
        if (!is_int($foyerId)) {
            throw new \RuntimeException((string) $foyerId);
        }
        $result = $this->assignUser($userId, $foyerId);
        if ($result !== true) {
            throw new \RuntimeException((string) $result);
        }

        return $foyerId;
    }

    /**
     * Garantit un foyer pour un compte seul : en crée un si besoin (« Mon foyer »).
     * Un utilisateur peut ainsi avoir sa collection sans rejoindre un groupe famille.
     */
    public function ensurePersonalFoyerForUser(int $userId, string $nom = self::PERSONAL_DEFAULT_NAME): int
    {
        if ($userId <= 0 || !self::tableExists($this->db)) {
            return 0;
        }

        $existing = $this->currentFoyerIdForUser($userId);
        if ($existing > 0) {
            return $existing;
        }

        return $this->createDefaultForUser($userId, $nom);
    }

    public function findForUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT f.id, f.nom, f.created_at
             FROM foyers f
             INNER JOIN utilisateurs u ON u.foyer_id = f.id
             WHERE u.id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function currentFoyerIdForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare('SELECT foyer_id FROM utilisateurs WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }
}

<?php
/**
 * Migration des foyers v0.7 vers groupes famille + group_members (phase 6).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class SocialMigration
{
    public const META_KEY = 'social_data_v1';

    public static function runIfNeeded(PDO $db): void
    {
        $migrator = new SchemaMigrator($db);
        if ($migrator->getMetadata(self::META_KEY) === '1') {
            return;
        }
        if (!FamilyGroupService::isAvailable()) {
            return;
        }

        $runner = new self($db);
        $runner->migrate();
        $migrator->setMetadata(self::META_KEY, '1');
    }

    public function __construct(private readonly PDO $db)
    {
    }

    private function migrate(): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->exec(
                "UPDATE foyers SET kind = '" . FamilyGroupService::KIND_FAMILLE . "'
                 WHERE kind IS NULL OR TRIM(kind) = ''"
            );

            $foyers = $this->db->query('SELECT id FROM foyers ORDER BY id ASC')->fetchAll();
            foreach ($foyers as $foyer) {
                $foyerId = (int) ($foyer['id'] ?? 0);
                if ($foyerId <= 0) {
                    continue;
                }
                $this->migrateFoyerMembers($foyerId);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function migrateFoyerMembers(int $foyerId): void
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM utilisateurs WHERE foyer_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$foyerId]);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($userIds === []) {
            return;
        }

        $founderId = (int) $userIds[0];
        $createdBy = $this->db->prepare('SELECT created_by_user_id FROM foyers WHERE id = ?');
        $createdBy->execute([$foyerId]);
        $existingCreator = $createdBy->fetchColumn();
        if (!is_numeric($existingCreator) || (int) $existingCreator <= 0) {
            $this->db->prepare('UPDATE foyers SET created_by_user_id = ? WHERE id = ?')
                ->execute([$founderId, $foyerId]);
        }

        foreach ($userIds as $userId) {
            $uid = (int) $userId;
            if ($uid <= 0) {
                continue;
            }
            $check = $this->db->prepare(
                'SELECT 1 FROM group_members WHERE foyer_id = ? AND user_id = ? LIMIT 1'
            );
            $check->execute([$foyerId, $uid]);
            if ($check->fetchColumn()) {
                continue;
            }
            $role = $uid === $founderId
                ? FamilyGroupService::ROLE_FOUNDER
                : FamilyGroupService::ROLE_MEMBER;
            $this->db->prepare(
                'INSERT INTO group_members (foyer_id, user_id, role, joined_at)
                 VALUES (?, ?, ?, datetime(\'now\'))'
            )->execute([$foyerId, $uid, $role]);
        }
    }
}

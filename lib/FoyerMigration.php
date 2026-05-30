<?php
/**
 * Migration des données existantes vers le modèle « foyers » (phase 4).
 *
 * Exécutée une seule fois après les migrations SQL 008–011.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FoyerMigration
{
    public const META_KEY = 'foyer_data_v1';

    public static function runIfNeeded(PDO $db): void
    {
        $migrator = new SchemaMigrator($db);
        if ($migrator->getMetadata(self::META_KEY) === '1') {
            return;
        }
        if (!FoyerRepository::tableExists($db)) {
            return;
        }
        if (!$migrator->tableExists('bibliotheque')) {
            return;
        }
        if ((int) $migrator->schemaVersion() < 11) {
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
            $defaultFoyerId = $this->ensureDefaultFoyer();
            $this->assignUsersWithoutFoyer($defaultFoyerId);
            $this->migrateCollectionRows($defaultFoyerId);
            $this->dedupeCollectionEntries();
            $this->backfillHistoriqueUserIds();
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function ensureDefaultFoyer(): int
    {
        $existing = $this->db->query('SELECT id FROM foyers ORDER BY id ASC LIMIT 1')->fetchColumn();
        if (is_numeric($existing) && (int) $existing > 0) {
            return (int) $existing;
        }

        $this->db->prepare(
            'INSERT INTO foyers (nom, created_at) VALUES (?, datetime(\'now\'))'
        )->execute([FoyerRepository::DEFAULT_NAME]);

        return (int) $this->db->lastInsertId();
    }

    private function assignUsersWithoutFoyer(int $defaultFoyerId): void
    {
        $this->db->prepare(
            'UPDATE utilisateurs SET foyer_id = ? WHERE foyer_id IS NULL OR foyer_id = 0'
        )->execute([$defaultFoyerId]);
    }

    private function migrateCollectionRows(int $defaultFoyerId): void
    {
        $rows = $this->db->query(
            "SELECT b.id, b.user_id, b.oeuvre_id, u.foyer_id AS user_foyer_id
             FROM bibliotheque b
             INNER JOIN utilisateurs u ON u.id = b.user_id
             WHERE b.statut = 'collection'"
        )->fetchAll();

        foreach ($rows as $row) {
            $libraryId = (int) ($row['id'] ?? 0);
            $foyerId = (int) ($row['user_foyer_id'] ?? 0);
            if ($foyerId <= 0) {
                $foyerId = $defaultFoyerId;
            }
            $this->db->prepare(
                'UPDATE bibliotheque SET foyer_id = ? WHERE id = ? AND statut = ?'
            )->execute([$foyerId, $libraryId, LibraryStatut::COLLECTION]);
        }

        $this->db->exec(
            "UPDATE bibliotheque SET foyer_id = NULL WHERE statut = 'wishlist'"
        );
    }

    private function dedupeCollectionEntries(): void
    {
        $groups = $this->db->query(
            "SELECT foyer_id, oeuvre_id, COUNT(*) AS cnt
             FROM bibliotheque
             WHERE statut = 'collection' AND foyer_id IS NOT NULL
             GROUP BY foyer_id, oeuvre_id
             HAVING cnt > 1"
        )->fetchAll();

        foreach ($groups as $group) {
            $foyerId = (int) ($group['foyer_id'] ?? 0);
            $oeuvreId = (int) ($group['oeuvre_id'] ?? 0);
            if ($foyerId <= 0 || $oeuvreId <= 0) {
                continue;
            }

            $stmt = $this->db->prepare(
                "SELECT id, user_id, support_physique, format_image, format_son, saga, saga_ordre,
                        saison_numero, saison_label, ean, created_at
                 FROM bibliotheque
                 WHERE foyer_id = ? AND oeuvre_id = ? AND statut = 'collection'
                 ORDER BY id ASC"
            );
            $stmt->execute([$foyerId, $oeuvreId]);
            $rows = $stmt->fetchAll();
            if (count($rows) < 2) {
                continue;
            }

            $keeper = $this->pickBestCollectionRow($rows);
            $keeperId = (int) ($keeper['id'] ?? 0);
            if ($keeperId <= 0) {
                continue;
            }

            foreach ($rows as $row) {
                $duplicateId = (int) ($row['id'] ?? 0);
                if ($duplicateId === $keeperId) {
                    continue;
                }
                $this->db->prepare('UPDATE historique SET film_id = ? WHERE film_id = ?')
                    ->execute([$keeperId, $duplicateId]);
                $this->db->prepare('DELETE FROM bibliotheque WHERE id = ?')->execute([$duplicateId]);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function pickBestCollectionRow(array $rows): array
    {
        $best = $rows[0];
        $bestScore = $this->collectionRowScore($best);

        foreach ($rows as $row) {
            $score = $this->collectionRowScore($row);
            if ($score > $bestScore) {
                $best = $row;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** @param array<string, mixed> $row */
    private function collectionRowScore(array $row): int
    {
        $score = 0;
        foreach ([
            'support_physique',
            'format_image',
            'format_son',
            'saga',
            'saison_label',
            'ean',
        ] as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                $score += 2;
            }
        }
        if ((int) ($row['saga_ordre'] ?? 0) > 0) {
            $score += 1;
        }
        if ((int) ($row['saison_numero'] ?? 0) > 0) {
            $score += 1;
        }

        return $score;
    }

    private function backfillHistoriqueUserIds(): void
    {
        $this->db->exec(
            'UPDATE historique
             SET user_id = (
                 SELECT b.user_id FROM bibliotheque b WHERE b.id = historique.film_id
             )
             WHERE user_id IS NULL OR user_id = 0'
        );
    }
}

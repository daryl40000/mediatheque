<?php
/**
 * Maintenance du catalogue : doublons, fiches incomplètes, fusion, nettoyage.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogMaintenance
{
    private PDO $db;

    private OeuvreRepository $oeuvres;

    private CatalogAuditLog $audit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->oeuvres = new OeuvreRepository();
        $this->audit = new CatalogAuditLog();
    }

    /**
     * @return array{
     *   total_oeuvres: int,
     *   duplicate_title_groups: int,
     *   duplicate_tmdb_groups: int,
     *   incomplete_count: int,
     *   orphan_posters: int,
     *   invalid_tmdb_count: int
     * }
     */
    public function dashboardStats(): array
    {
        return [
            'total_oeuvres' => (int) $this->db->query('SELECT COUNT(*) FROM oeuvres')->fetchColumn(),
            'duplicate_title_groups' => count($this->findDuplicateGroupsByTitle()),
            'duplicate_tmdb_groups' => count($this->findDuplicateGroupsByTmdb()),
            'incomplete_count' => count($this->findIncompleteOeuvres()),
            'orphan_posters' => count($this->findOrphanPosterFiles()),
            'invalid_tmdb_count' => count($this->findDuplicateTmdbOeuvreIds()),
        ];
    }

    /**
     * Doublons probables : même titre + réalisateur (normalisation espaces / casse).
     *
     * @return list<array{key: string, titre: string, realisateur: string, ids: list<int>, count: int}>
     */
    public function findDuplicateGroupsByTitle(): array
    {
        $rows = $this->db->query(
            'SELECT id, titre, realisateur
             FROM oeuvres
             ORDER BY titre COLLATE FRENCH_NOCASE, realisateur COLLATE FRENCH_NOCASE'
        )->fetchAll() ?: [];

        /** @var array<string, list<array<string, mixed>>> $groups */
        $groups = [];
        foreach ($rows as $row) {
            $key = $this->normalizedPairKey(
                (string) ($row['titre'] ?? ''),
                (string) ($row['realisateur'] ?? '')
            );
            if ($key === '|') {
                continue;
            }
            $groups[$key][] = $row;
        }

        $out = [];
        foreach ($groups as $key => $items) {
            if (count($items) < 2) {
                continue;
            }
            $ids = array_values(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $items));
            $first = $items[0];
            $out[] = [
                'key' => $key,
                'titre' => (string) ($first['titre'] ?? ''),
                'realisateur' => (string) ($first['realisateur'] ?? ''),
                'ids' => $ids,
                'count' => count($ids),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $out;
    }

    /**
     * @return list<array{tmdb_id: int, ids: list<int>, count: int}>
     */
    public function findDuplicateGroupsByTmdb(): array
    {
        $rows = $this->db->query(
            'SELECT tmdb_id, GROUP_CONCAT(id) AS ids, COUNT(*) AS c
             FROM oeuvres
             WHERE tmdb_id > 0
             GROUP BY tmdb_id
             HAVING c > 1
             ORDER BY c DESC, tmdb_id ASC'
        )->fetchAll() ?: [];

        $out = [];
        foreach ($rows as $row) {
            $ids = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) ($row['ids'] ?? ''))
            )));
            if ($ids === []) {
                continue;
            }
            $out[] = [
                'tmdb_id' => (int) ($row['tmdb_id'] ?? 0),
                'ids' => $ids,
                'count' => (int) ($row['c'] ?? count($ids)),
            ];
        }

        return $out;
    }

    /**
     * @return list<int> IDs d’œuvres impliquées dans un doublon TMDB
     */
    public function findDuplicateTmdbOeuvreIds(): array
    {
        $ids = [];
        foreach ($this->findDuplicateGroupsByTmdb() as $group) {
            foreach ($group['ids'] as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Fiches sans synopsis, sans affiche utilisable et sans lien TMDB.
     *
     * @return list<array<string, mixed>>
     */
    public function findIncompleteOeuvres(int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare(
            'SELECT o.*, (
                SELECT COUNT(*) FROM bibliotheque b WHERE b.oeuvre_id = o.id
             ) AS library_count
             FROM oeuvres o
             WHERE TRIM(o.synopsis) = ""
               AND (TRIM(o.poster_url) = "" OR o.poster_url IS NULL)
               AND COALESCE(o.tmdb_id, 0) = 0
             ORDER BY o.titre COLLATE FRENCH_NOCASE
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<string> Chemins absolus des fichiers orphelins
     */
    public function findOrphanPosterFiles(): array
    {
        $dir = PosterStorage::postersFilesystemDir();
        if (!is_dir($dir)) {
            return [];
        }

        $referenced = [];
        $stmt = $this->db->query(
            "SELECT id, poster_url FROM oeuvres WHERE TRIM(poster_url) LIKE '/posters/%'"
        );
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $path = PosterStorage::filesystemPathFromWeb((string) ($row['poster_url'] ?? ''));
            if ($path !== null && is_file($path)) {
                $referenced[realpath($path) ?: $path] = true;
            }
        }

        $orphans = [];
        foreach (glob($dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $resolved = realpath($file) ?: $file;
            if (!isset($referenced[$resolved])) {
                $orphans[] = $resolved;
            }
        }

        sort($orphans);

        return $orphans;
    }

    /**
     * Fusionne removeId dans keepId (bibliothèques et historique conservés).
     *
     * @return true|string
     */
    public function mergeOeuvres(int $keepId, int $removeId, int $adminUserId): bool|string
    {
        if ($keepId <= 0 || $removeId <= 0) {
            return 'Identifiants invalides.';
        }
        if ($keepId === $removeId) {
            return 'Choisissez deux fiches différentes.';
        }

        $keep = $this->oeuvres->findById($keepId);
        $remove = $this->oeuvres->findById($removeId);
        if ($keep === null || $remove === null) {
            return 'Une des fiches est introuvable.';
        }

        $this->db->beginTransaction();
        try {
            $this->mergeOeuvreMetadata($keep, $remove);
            $this->reassignBibliothequeEntries($keepId, $removeId);
            (new PosterStorage())->deleteLocalForOeuvre($removeId);
            if (!$this->oeuvres->deleteById($removeId)) {
                throw new \RuntimeException('Impossible de supprimer la fiche fusionnée.');
            }

            $this->audit->log(
                $adminUserId,
                CatalogAuditLog::ACTION_MERGE,
                $keepId,
                'Fusion #' . $removeId . ' → #' . $keepId
                . ' (« ' . (string) ($remove['titre'] ?? '') . ' »)'
            );

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Fusion impossible : ' . $e->getMessage();
        }
    }

    /**
     * @return array{deleted: int, errors: list<string>}
     */
    public function purgeOrphanPosters(int $adminUserId): array
    {
        $orphans = $this->findOrphanPosterFiles();
        $deleted = 0;
        $errors = [];

        foreach ($orphans as $path) {
            if (@unlink($path)) {
                $deleted++;
            } else {
                $errors[] = basename($path);
            }
        }

        if ($deleted > 0) {
            $this->audit->log(
                $adminUserId,
                CatalogAuditLog::ACTION_PURGE_POSTERS,
                null,
                $deleted . ' fichier(s) supprimé(s)'
            );
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $keep
     * @param array<string, mixed> $remove
     */
    private function mergeOeuvreMetadata(array $keep, array $remove): void
    {
        $keepId = (int) ($keep['id'] ?? 0);
        $removeId = (int) ($remove['id'] ?? 0);
        $updates = [];

        foreach (CatalogSchema::OEUVRE_FIELDS as $field) {
            $keepVal = trim((string) ($keep[$field] ?? ''));
            $removeVal = trim((string) ($remove[$field] ?? ''));
            if ($field === 'duree_min' || $field === 'annee' || str_ends_with($field, '_tmdb_id') || $field === 'tmdb_id') {
                if ((int) ($keep[$field] ?? 0) > 0) {
                    continue;
                }
                if ((int) ($remove[$field] ?? 0) <= 0) {
                    continue;
                }
                $updates[$field] = (int) $remove[$field];
                continue;
            }
            if ($keepVal !== '' || $removeVal === '') {
                continue;
            }
            $updates[$field] = $remove[$field];
        }

        if ($updates !== []) {
            $this->oeuvres->update($keepId, $updates, array_keys($updates));
        }

        $keepPoster = trim((string) ($keep['poster_url'] ?? ''));
        $removePoster = trim((string) ($remove['poster_url'] ?? ''));
        if ($keepPoster === '' && $removePoster !== '') {
            $this->relocatePosterFile($removeId, $keepId, $removePoster);
        }
    }

    private function relocatePosterFile(int $fromId, int $toId, string $posterUrl): void
    {
        if (!PosterStorage::isLocalWebPath($posterUrl)) {
            $this->oeuvres->update($toId, ['poster_url' => $posterUrl], ['poster_url']);

            return;
        }

        $binary = $this->readLocalPosterBinary($fromId);
        if ($binary === null) {
            $this->oeuvres->update($toId, ['poster_url' => $posterUrl], ['poster_url']);

            return;
        }

        $newPath = (new PosterStorage())->importBinaryForOeuvre($toId, $binary);
        if ($newPath !== '') {
            $this->oeuvres->update($toId, ['poster_url' => $newPath], ['poster_url']);
        }
    }

    private function readLocalPosterBinary(int $oeuvreId): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = PosterStorage::postersFilesystemDir() . '/' . $oeuvreId . '.' . $ext;
            if (!is_file($path)) {
                continue;
            }
            $binary = file_get_contents($path);
            if ($binary !== false && $binary !== '') {
                return $binary;
            }
        }

        return null;
    }

    private function reassignBibliothequeEntries(int $keepId, int $removeId): void
    {
        $stmt = $this->db->prepare('SELECT * FROM bibliotheque WHERE oeuvre_id = ?');
        $stmt->execute([$removeId]);
        $entries = $stmt->fetchAll() ?: [];

        foreach ($entries as $entry) {
            $entryId = (int) ($entry['id'] ?? 0);
            $userId = (int) ($entry['user_id'] ?? 0);
            if ($entryId <= 0 || $userId <= 0) {
                continue;
            }

            $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);
            $existing = (new BibliothequeRepository())->findByOeuvreId($keepId, $userId, $foyerId);
            if ($existing === null) {
                $this->db->prepare('UPDATE bibliotheque SET oeuvre_id = ? WHERE id = ?')
                    ->execute([$keepId, $entryId]);
                continue;
            }

            $keepEntryId = (int) ($existing['id'] ?? 0);
            $this->mergeBibliothequePersonalFields($keepEntryId, $entry);
            $this->db->prepare('UPDATE historique SET film_id = ? WHERE film_id = ?')
                ->execute([$keepEntryId, $entryId]);
            $this->db->prepare('DELETE FROM bibliotheque WHERE id = ?')->execute([$entryId]);
        }
    }

    /**
     * @param array<string, mixed> $from
     */
    private function mergeBibliothequePersonalFields(int $keepEntryId, array $from): void
    {
        $keepStmt = $this->db->prepare('SELECT * FROM bibliotheque WHERE id = ?');
        $keepStmt->execute([$keepEntryId]);
        $keep = $keepStmt->fetch();
        if ($keep === false) {
            return;
        }

        $updates = [];
        foreach (CatalogSchema::LIBRARY_FIELDS as $field) {
            $keepVal = trim((string) ($keep[$field] ?? ''));
            $fromVal = trim((string) ($from[$field] ?? ''));
            if ($field === 'saga_ordre' || $field === 'saison_numero') {
                if ((int) ($keep[$field] ?? 0) > 0 || (int) ($from[$field] ?? 0) <= 0) {
                    continue;
                }
                $updates[$field] = $from[$field];
                continue;
            }
            if ($keepVal !== '' || $fromVal === '') {
                continue;
            }
            $updates[$field] = $from[$field];
        }

        if ($updates !== []) {
            (new BibliothequeRepository())->update($keepEntryId, $updates);
        }
    }

    private function normalizedPairKey(string $titre, string $realisateur): string
    {
        return mb_strtolower(trim($titre), 'UTF-8')
            . '|'
            . mb_strtolower(trim($realisateur), 'UTF-8');
    }
}

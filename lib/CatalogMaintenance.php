<?php
/**
 * Maintenance du catalogue : doublons, fiches incomplètes, fusion, nettoyage.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogMaintenance
{
    public const DUPLICATE_GROUP_TITLE = 'title';
    public const DUPLICATE_GROUP_TMDB = 'tmdb';
    public const DUPLICATE_GROUP_MAGAZINE = 'magazine';

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
     *   duplicate_magazine_groups: int,
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
            'duplicate_magazine_groups' => count($this->findDuplicateMagazineIssueGroups()),
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

        $dismissed = $this->loadDismissedGroupKeys(self::DUPLICATE_GROUP_TITLE);

        $out = [];
        foreach ($groups as $key => $items) {
            if (count($items) < 2 || isset($dismissed[$key])) {
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
                'oeuvres' => $this->oeuvreSummariesForIds($ids),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $out;
    }

    /**
     * Doublons magazine : même série, même libellé de numéro et même statut hors-série.
     *
     * @return list<array{
     *   series_id: int,
     *   series_titre: string,
     *   numero: string,
     *   est_hors_serie: bool,
     *   ids: list<int>,
     *   count: int,
     *   oeuvres: list<array<string, mixed>>
     * }>
     */
    public function findDuplicateMagazineIssueGroups(): array
    {
        if (!MagazineRepository::isAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT om.series_id, LOWER(TRIM(om.numero)) AS numero_key,
                    MIN(TRIM(om.numero)) AS numero_label,
                    om.est_hors_serie,
                    GROUP_CONCAT(om.oeuvre_id) AS ids,
                    COUNT(*) AS c,
                    s.titre AS series_titre
             FROM oeuvre_magazine om
             INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = ?
             INNER JOIN series s ON s.id = om.series_id
             GROUP BY om.series_id, numero_key, om.est_hors_serie
             HAVING c > 1
             ORDER BY c DESC, s.titre COLLATE FRENCH_NOCASE, numero_key ASC'
        );
        $stmt->execute([MediaDomain::MAGAZINE]);
        $rows = $stmt->fetchAll() ?: [];

        $dismissed = $this->loadDismissedGroupKeys(self::DUPLICATE_GROUP_MAGAZINE);

        $out = [];
        foreach ($rows as $row) {
            $ids = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) ($row['ids'] ?? ''))
            )));
            if (count($ids) < 2) {
                continue;
            }
            $seriesId = (int) ($row['series_id'] ?? 0);
            $numeroKey = (string) ($row['numero_key'] ?? '');
            $estHorsSerie = (int) ($row['est_hors_serie'] ?? 0) === 1;
            $key = $this->magazineDuplicateGroupKey($seriesId, $numeroKey, $estHorsSerie);
            if (isset($dismissed[$key])) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'series_id' => $seriesId,
                'series_titre' => (string) ($row['series_titre'] ?? ''),
                'numero' => (string) ($row['numero_label'] ?? ''),
                'est_hors_serie' => (int) ($row['est_hors_serie'] ?? 0) === 1,
                'ids' => $ids,
                'count' => count($ids),
                'oeuvres' => $this->oeuvreSummariesForIds($ids),
            ];
        }

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

        $dismissed = $this->loadDismissedGroupKeys(self::DUPLICATE_GROUP_TMDB);

        $out = [];
        foreach ($rows as $row) {
            $ids = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) ($row['ids'] ?? ''))
            )));
            if ($ids === []) {
                continue;
            }
            $tmdbId = (int) ($row['tmdb_id'] ?? 0);
            $key = $this->tmdbDuplicateGroupKey($tmdbId);
            if (isset($dismissed[$key])) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'tmdb_id' => $tmdbId,
                'ids' => $ids,
                'count' => (int) ($row['c'] ?? count($ids)),
                'oeuvres' => $this->oeuvreSummariesForIds($ids),
            ];
        }

        return $out;
    }

    /**
     * Résumés catalogue pour comparer des doublons avant fusion.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function oeuvreSummariesForIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            'SELECT o.id, o.titre, o.realisateur, o.annee, o.poster_url, o.tmdb_id, o.media_domain, o.synopsis,
                om.numero AS mag_numero, om.est_hors_serie AS mag_est_hors_serie,
                s.titre AS mag_series_titre,
                (SELECT COUNT(*) FROM bibliotheque b WHERE b.oeuvre_id = o.id) AS library_count
             FROM oeuvres o
             LEFT JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
             LEFT JOIN series s ON s.id = om.series_id
             WHERE o.id IN (' . $placeholders . ')
             ORDER BY o.id ASC'
        );
        $stmt->execute($ids);

        return $stmt->fetchAll() ?: [];
    }

    /** Libellé lisible pour les listes déroulantes de fusion. */
    public static function mergeOptionLabel(array $oeuvre): string
    {
        $parts = ['#' . (int) ($oeuvre['id'] ?? 0)];
        $titre = trim((string) ($oeuvre['titre'] ?? ''));
        $magNumero = trim((string) ($oeuvre['mag_numero'] ?? ''));
        $seriesTitre = trim((string) ($oeuvre['mag_series_titre'] ?? ''));
        if (
            MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? '')) === MediaDomain::MAGAZINE
            && $seriesTitre !== ''
            && $magNumero !== ''
        ) {
            $parts[] = $seriesTitre . ' — n°' . $magNumero
                . (!empty($oeuvre['mag_est_hors_serie']) ? ' (HS)' : '');
        } elseif ($titre !== '') {
            $parts[] = $titre;
        }
        $realisateur = trim((string) ($oeuvre['realisateur'] ?? ''));
        if ($realisateur !== '') {
            $parts[] = $realisateur;
        }
        $annee = (int) ($oeuvre['annee'] ?? 0);
        if ($annee > 0) {
            $parts[] = '(' . $annee . ')';
        }
        $libraryCount = (int) ($oeuvre['library_count'] ?? 0);
        $parts[] = $libraryCount . ' bib.';
        $tmdbId = (int) ($oeuvre['tmdb_id'] ?? 0);
        if ($tmdbId > 0) {
            $parts[] = 'TMDB ' . $tmdbId;
        }
        if (trim((string) ($oeuvre['poster_url'] ?? '')) !== '') {
            $parts[] = 'affiche';
        }
        if (trim((string) ($oeuvre['synopsis'] ?? '')) !== '') {
            $parts[] = 'synopsis';
        }

        return implode(' — ', $parts);
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

        $referenced = SeriesPoster::referencedFilesystemPaths($this->db);

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
     * Marque un groupe de doublons comme légitime : les fiches restent séparées.
     *
     * @return true|string
     */
    public function dismissDuplicateGroup(string $groupType, string $groupKey, int $adminUserId): bool|string
    {
        $groupType = trim($groupType);
        $groupKey = trim($groupKey);
        if (!in_array($groupType, [
            self::DUPLICATE_GROUP_TITLE,
            self::DUPLICATE_GROUP_TMDB,
            self::DUPLICATE_GROUP_MAGAZINE,
        ], true)) {
            return 'Type de doublon invalide.';
        }
        if ($groupKey === '') {
            return 'Groupe de doublons invalide.';
        }
        if (!$this->duplicateDismissalTableExists()) {
            return 'Fonction indisponible (migration en cours).';
        }

        $this->db->prepare(
            'INSERT INTO catalog_duplicate_dismissal (group_type, group_key, dismissed_by_user_id, dismissed_at)
             VALUES (?, ?, ?, datetime(\'now\'))
             ON CONFLICT(group_type, group_key) DO UPDATE SET
                dismissed_by_user_id = excluded.dismissed_by_user_id,
                dismissed_at = datetime(\'now\')'
        )->execute([
            $groupType,
            $groupKey,
            max(0, $adminUserId),
        ]);

        $this->audit->log(
            $adminUserId,
            CatalogAuditLog::ACTION_DISMISS_DUPLICATE,
            null,
            'Groupe ' . $groupType . ' : ' . $groupKey
        );

        return true;
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

        $keep = $this->oeuvres->findByIdForAdmin($keepId);
        $remove = $this->oeuvres->findByIdForAdmin($removeId);
        if ($keep === null || $remove === null) {
            return 'Une des fiches est introuvable.';
        }

        $keepDomain = MediaDomain::normalize((string) ($keep['media_domain'] ?? MediaDomain::FILM));
        $removeDomain = MediaDomain::normalize((string) ($remove['media_domain'] ?? MediaDomain::FILM));
        if ($keepDomain !== $removeDomain) {
            return 'Les deux fiches doivent être du même type de média.';
        }

        $this->db->beginTransaction();
        try {
            $this->mergeOeuvreMetadata($keep, $remove);
            $this->reassignBibliothequeEntries($keepId, $removeId);
            if (GameSteamAppIdMapRepository::isAvailable()) {
                (new GameSteamAppIdMapRepository())->reassignOnOeuvreMerge($keepId, $removeId);
            }
            if (OeuvreStoreLinkRepository::isAvailable()) {
                (new OeuvreStoreLinkRepository())->reassignOnOeuvreMerge($keepId, $removeId);
            }
            $this->transferSteamAppIdOnMerge($keepId, $removeId);
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

    private function transferSteamAppIdOnMerge(int $keepId, int $removeId): void
    {
        if (!GameSchema::hasSteamAppIdColumn() || $keepId <= 0 || $removeId <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT oeuvre_id, steam_appid FROM oeuvre_jeu WHERE oeuvre_id IN (?, ?)'
        );
        $stmt->execute([$keepId, $removeId]);
        $byOeuvre = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $byOeuvre[(int) ($row['oeuvre_id'] ?? 0)] = (int) ($row['steam_appid'] ?? 0);
        }

        $removeAppid = (int) ($byOeuvre[$removeId] ?? 0);
        $keepAppid = (int) ($byOeuvre[$keepId] ?? 0);
        if ($removeAppid <= 0 || $keepAppid > 0) {
            return;
        }

        $this->db->prepare('UPDATE oeuvre_jeu SET steam_appid = ? WHERE oeuvre_id = ?')
            ->execute([$removeAppid, $keepId]);
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

    private function tmdbDuplicateGroupKey(int $tmdbId): string
    {
        return 'tmdb:' . max(0, $tmdbId);
    }

    private function magazineDuplicateGroupKey(int $seriesId, string $numeroKey, bool $estHorsSerie): string
    {
        return $seriesId
            . '|'
            . mb_strtolower(trim($numeroKey), 'UTF-8')
            . '|'
            . ($estHorsSerie ? '1' : '0');
    }

    public static function duplicateDismissalTableExists(): bool
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'catalog_duplicate_dismissal' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<string, true>
     */
    private function loadDismissedGroupKeys(string $groupType): array
    {
        if (!$this->duplicateDismissalTableExists()) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT group_key FROM catalog_duplicate_dismissal WHERE group_type = ?'
        );
        $stmt->execute([$groupType]);

        $keys = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['group_key'] ?? ''));
            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return $keys;
    }
}

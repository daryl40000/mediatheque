<?php
/**
 * Numéros de magazines : catalogue (oeuvres + oeuvre_magazine) et collection.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return SeriesRepository::tableExists()
            && self::seriesLibraryTableExists()
            && CatalogSchema::usesCatalogTables(Database::getInstance());
    }

    public static function seriesLibraryTableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'series_bibliotheque' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** Ajoute une série à la collection ou aux envies (sans numéro). */
    public function registerSeriesInLibrary(
        int $seriesId,
        string $statut,
        int $userId,
        int $foyerId
    ): bool|string {
        if (!self::seriesLibraryTableExists() || $seriesId <= 0) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        if ($statut === LibraryStatut::COLLECTION) {
            $this->db->prepare(
                'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
                 VALUES (?, ?, ?, ?)'
            )->execute([$seriesId, $userId, $foyerId, LibraryStatut::COLLECTION]);

            return true;
        }

        $this->db->prepare(
            'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
             VALUES (?, ?, ?, ?)'
        )->execute([$seriesId, $userId, $foyerId, LibraryStatut::WISHLIST]);

        return true;
    }

    /**
     * Séries présentes dans la collection ou les envies de l’utilisateur.
     *
     * @return list<array<string, mixed>>
     */
    public function listSeriesInLibrary(
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $query = ''
    ): array {
        if (!self::isAvailable()) {
            return [];
        }

        $params = [
            'col_stat_1' => LibraryStatut::COLLECTION,
            'col_stat_2' => LibraryStatut::COLLECTION,
            'col_stat_3' => LibraryStatut::COLLECTION,
            'domain_series' => MediaDomain::MAGAZINE,
            'domain_oeuvre' => MediaDomain::MAGAZINE,
        ];

        [$seriesStatutSql, $seriesStatutParams] = $this->seriesLibraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge($params, $seriesStatutParams);

        $where = [
            's.media_domain = :domain_series',
            $seriesStatutSql,
        ];

        if (trim($query) !== '') {
            $where[] = 'LOWER(s.titre) LIKE LOWER(:q) ESCAPE \'\\\'';
            $params['q'] = LikePattern::containsFragment(trim($query));
        }

        $order = $this->seriesOrderClause($sortBy, $sortDir);

        // Séries suivies (series_bibliotheque) + numéros optionnels (LEFT JOIN).
        $sql = 'SELECT s.*,
                    COUNT(DISTINCT CASE WHEN b.statut = :col_stat_1 THEN b.id END) AS issue_count,
                    MAX(CASE WHEN b.statut = :col_stat_2 THEN om.numero_ordre END) AS last_numero_ordre,
                    MAX(CASE WHEN b.statut = :col_stat_3 THEN om.date_parution END) AS last_date_parution,
                    MAX(CASE WHEN TRIM(o.poster_url) != \'\' THEN o.poster_url END) AS latest_poster_url
                FROM series s
                INNER JOIN series_bibliotheque sb ON sb.series_id = s.id
                LEFT JOIN oeuvre_magazine om ON om.series_id = s.id
                LEFT JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain_oeuvre
                LEFT JOIN bibliotheque b ON b.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY s.id
                ORDER BY ' . $order;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countSeriesInLibrary(int $userId, int $foyerId, ?string $statut = null, string $query = ''): int
    {
        return count($this->listSeriesInLibrary($userId, $foyerId, $statut, 'titre', 'asc', $query));
    }

    /** Nombre total de numéros en collection ou en envies. */
    public function countIssuesInLibrary(int $userId, int $foyerId, ?string $statut = null): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $params = [
            'domain_oeuvre' => MediaDomain::MAGAZINE,
        ];

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge($params, $statutParams);

        $sql = 'SELECT COUNT(DISTINCT b.id)
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain_oeuvre
                INNER JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
                WHERE ' . $statutSql;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return (int) $stmt->fetchColumn();
    }

    /**
     * Numéros d’une série dans la bibliothèque.
     *
     * @return list<array<string, mixed>>
     */
    public function listIssuesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'numero_ordre',
        string $sortDir = 'desc'
    ): array {
        if (!self::isAvailable() || $seriesId <= 0) {
            return [];
        }

        $params = [
            'series_id' => $seriesId,
            'domain' => MediaDomain::MAGAZINE,
        ];

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge($params, $statutParams);

        $order = $this->issueOrderClause($sortBy, $sortDir);

        $sql = 'SELECT b.id AS bib_id, b.statut, b.support_physique, b.created_at AS bib_created_at,
                    o.id AS oeuvre_id, o.titre, o.poster_url,
                    om.numero, om.numero_ordre, om.date_parution, om.sommaire, om.pages,
                    om.est_hors_serie, om.stored_object_id,
                    s.titre AS series_titre, s.publication_type
                FROM oeuvre_magazine om
                INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain
                INNER JOIN series s ON s.id = om.series_id
                INNER JOIN bibliotheque b ON b.oeuvre_id = o.id
                WHERE om.series_id = :series_id AND ' . $statutSql . '
                ORDER BY ' . $order;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findIssueByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        if (!self::isAvailable() || $bibId <= 0) {
            return null;
        }

        $params = [
            'bib_id' => $bibId,
            'user_id' => $userId,
            'foyer_id' => $foyerId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'domain' => MediaDomain::MAGAZINE,
        ];

        $stmt = $this->db->prepare(
            'SELECT b.id AS bib_id, b.statut, b.support_physique, b.user_id, b.foyer_id,
                    o.id AS oeuvre_id, o.titre, o.poster_url,
                    om.series_id, om.numero, om.numero_ordre, om.date_parution, om.sommaire,
                    om.pages, om.est_hors_serie, om.stored_object_id,
                    s.titre AS series_titre, s.publication_type, s.editeur, s.issn, s.poster_url AS series_poster_url
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
             INNER JOIN series s ON s.id = om.series_id
             WHERE b.id = :bib_id
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )
             LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function maxNumeroOrdreForSeries(int $seriesId): float
    {
        if ($seriesId <= 0) {
            return 0.0;
        }

        $stmt = $this->db->prepare(
            'SELECT MAX(numero_ordre) FROM oeuvre_magazine WHERE series_id = ?'
        );
        $stmt->execute([$seriesId]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Crée un numéro catalogue + entrée bibliothèque.
     *
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createIssueWithLibrary(
        int $seriesId,
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        if (!self::isAvailable()) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $numero = trim((string) ($data['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $numeroOrdre = (float) ($data['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = is_numeric($numero) ? (float) $numero : $this->maxNumeroOrdreForSeries($seriesId) + 1;
        }

        $horsSerie = !empty($data['est_hors_serie']);
        if ($horsSerie && $numeroOrdre === (float) (int) $numeroOrdre) {
            $numeroOrdre += 0.5;
        }

        $titre = trim((string) ($series['titre'] ?? '')) . ' — n°' . $numero;
        $dateParution = trim((string) ($data['date_parution'] ?? ''));
        $sommaire = trim((string) ($data['sommaire'] ?? ''));
        $pages = max(0, (int) ($data['pages'] ?? 0));
        $support = trim((string) ($data['support_physique'] ?? ''));

        $statut = LibraryStatut::normalize($statut);

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'synopsis' => '',
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::MAGAZINE,
            ]);

            $this->db->prepare(
                'INSERT INTO oeuvre_magazine (
                    oeuvre_id, series_id, numero, numero_ordre, date_parution,
                    sommaire, pages, est_hors_serie, stored_object_id
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $oeuvreId,
                $seriesId,
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire,
                $pages,
                $horsSerie ? 1 : 0,
                isset($data['stored_object_id']) ? (int) $data['stored_object_id'] : null,
            ]);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => $support,
            ]);

            $this->db->commit();

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('MagazineRepository::createIssueWithLibrary: ' . $e->getMessage());

            return 'Impossible d’enregistrer le numéro.';
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateIssue(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        $issue = $this->findIssueByBibId($bibId, $userId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        $numero = trim((string) ($data['numero'] ?? $issue['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $series = (new SeriesRepository())->findById($seriesId);
        $seriesTitre = trim((string) ($series['titre'] ?? ''));
        $titre = $seriesTitre !== '' ? $seriesTitre . ' — n°' . $numero : (string) ($issue['titre'] ?? '');

        $numeroOrdre = (float) ($data['numero_ordre'] ?? $issue['numero_ordre'] ?? 0);
        $horsSerie = !empty($data['est_hors_serie']);
        $dateParution = trim((string) ($data['date_parution'] ?? $issue['date_parution'] ?? ''));
        $sommaire = trim((string) ($data['sommaire'] ?? $issue['sommaire'] ?? ''));
        $pages = max(0, (int) ($data['pages'] ?? $issue['pages'] ?? 0));
        $posterUrl = trim((string) ($data['poster_url'] ?? $issue['poster_url'] ?? ''));

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'poster_url' => $posterUrl,
            ]);

            $storedObjectId = null;
            if (array_key_exists('stored_object_id', $data)) {
                $storedObjectId = $data['stored_object_id'] !== null ? (int) $data['stored_object_id'] : null;
            } elseif ((int) ($issue['stored_object_id'] ?? 0) > 0) {
                $storedObjectId = (int) $issue['stored_object_id'];
            }

            $this->db->prepare(
                'UPDATE oeuvre_magazine SET
                    numero = ?, numero_ordre = ?, date_parution = ?, sommaire = ?,
                    pages = ?, est_hors_serie = ?, stored_object_id = ?
                 WHERE oeuvre_id = ?'
            )->execute([
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire,
                $pages,
                $horsSerie ? 1 : 0,
                $storedObjectId,
                $oeuvreId,
            ]);

            if (isset($data['support_physique'])) {
                $this->db->prepare('UPDATE bibliotheque SET support_physique = ? WHERE id = ?')
                    ->execute([trim((string) $data['support_physique']), $bibId]);
            }

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Mise à jour impossible.';
        }
    }

    public function deleteFromLibrary(int $bibId, int $userId, int $foyerId): bool|string
    {
        $issue = $this->findIssueByBibId($bibId, $userId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        $stmt = $this->db->prepare('DELETE FROM bibliotheque WHERE id = ?');
        $stmt->execute([$bibId]);

        return $stmt->rowCount() > 0 ? true : 'Suppression impossible.';
    }

    /**
     * Enregistre un PDF pour un numéro (stored_objects + oeuvre_magazine).
     *
     * @return true|string
     */
    public function attachPdf(int $oeuvreId, string $tmpPath, string $originalName, int $fileSize): bool|string
    {
        if ($oeuvreId <= 0 || !is_readable($tmpPath)) {
            return 'Fichier PDF invalide.';
        }

        $maxBytes = 50 * 1024 * 1024;
        if ($fileSize <= 0 || $fileSize > $maxBytes) {
            return 'PDF trop volumineux (maximum 50 Mo).';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        if ($mime !== 'application/pdf') {
            return 'Le fichier doit être un PDF.';
        }

        $layout = MediaStorage::ensureLayout();
        if ($layout !== true) {
            return (string) $layout;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($originalName)) ?: 'numero.pdf';
        $relative = MediaStorage::relativePath('magazine', (string) $oeuvreId, $safeName);
        $absolute = MediaStorage::absolutePath($relative);
        if ($absolute === '') {
            return 'Chemin de stockage invalide.';
        }

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'Impossible de créer le dossier médias.';
        }

        if (!move_uploaded_file($tmpPath, $absolute) && !rename($tmpPath, $absolute)) {
            if (!copy($tmpPath, $absolute)) {
                return 'Impossible d’enregistrer le PDF.';
            }
        }

        @chmod($absolute, 0640);

        $stored = (new StoredObjectRepository())->create($relative, $fileSize, 'application/pdf');
        if ($stored === null) {
            @unlink($absolute);

            return 'Enregistrement du PDF en base impossible.';
        }

        $this->db->prepare('UPDATE oeuvre_magazine SET stored_object_id = ? WHERE oeuvre_id = ?')
            ->execute([(int) $stored['id'], $oeuvreId]);

        return true;
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function seriesLibraryStatutFilter(?string $statut, int $userId, int $foyerId): array
    {
        $statut = $statut !== null ? LibraryStatut::normalize($statut) : null;

        if ($statut === LibraryStatut::COLLECTION) {
            return [
                '(sb.statut = :sb_collection AND sb.foyer_id = :sb_foyer_id)',
                [
                    'sb_collection' => LibraryStatut::COLLECTION,
                    'sb_foyer_id' => $foyerId,
                ],
            ];
        }

        if ($statut === LibraryStatut::WISHLIST) {
            return [
                '(sb.statut = :sb_wishlist AND sb.user_id = :sb_user_id)',
                [
                    'sb_wishlist' => LibraryStatut::WISHLIST,
                    'sb_user_id' => $userId,
                ],
            ];
        }

        return [
            '((sb.statut = :sb_collection_scope AND sb.foyer_id = :sb_foyer_scope)
              OR (sb.statut = :sb_wishlist_scope AND sb.user_id = :sb_user_scope))',
            [
                'sb_collection_scope' => LibraryStatut::COLLECTION,
                'sb_wishlist_scope' => LibraryStatut::WISHLIST,
                'sb_foyer_scope' => $foyerId,
                'sb_user_scope' => $userId,
            ],
        ];
    }

    /** @param array<string, int|string> $params */
    private function libraryStatutFilter(?string $statut, int $userId, int $foyerId): array
    {
        $statut = $statut !== null ? LibraryStatut::normalize($statut) : null;

        if ($statut === LibraryStatut::COLLECTION) {
            return [
                '(b.statut = :collection_filter AND b.foyer_id = :foyer_id)',
                [
                    'collection_filter' => LibraryStatut::COLLECTION,
                    'foyer_id' => $foyerId,
                ],
            ];
        }

        if ($statut === LibraryStatut::WISHLIST) {
            return [
                '(b.statut = :wishlist_filter AND b.user_id = :user_id)',
                [
                    'wishlist_filter' => LibraryStatut::WISHLIST,
                    'user_id' => $userId,
                ],
            ];
        }

        return [
            '((b.statut = :collection_scope AND b.foyer_id = :foyer_id)
              OR (b.statut = :wishlist_scope AND b.user_id = :user_id))',
            [
                'collection_scope' => LibraryStatut::COLLECTION,
                'wishlist_scope' => LibraryStatut::WISHLIST,
                'foyer_id' => $foyerId,
                'user_id' => $userId,
            ],
        ];
    }

    /**
     * SQLite PDO refuse les paramètres nommés absents de la requête.
     *
     * @param array<string, int|string> $params
     * @return array<string, int|string>
     */
    private function filterParamsForSql(string $sql, array $params): array
    {
        if (!preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches)) {
            return [];
        }

        $filtered = [];
        foreach (array_unique($matches[1]) as $name) {
            if (array_key_exists($name, $params)) {
                $filtered[$name] = $params[$name];
            }
        }

        return $filtered;
    }

    private function seriesOrderClause(string $sortBy, string $sortDir): string
    {
        $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        return match ($sortBy) {
            'issues' => 'issue_count ' . $dir . ', s.titre COLLATE FRENCH_NOCASE ASC',
            'last_date' => 'last_date_parution ' . $dir . ', s.titre COLLATE FRENCH_NOCASE ASC',
            default => 's.titre COLLATE FRENCH_NOCASE ' . $dir,
        };
    }

    private function issueOrderClause(string $sortBy, string $sortDir): string
    {
        $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        return match ($sortBy) {
            'numero' => 'om.numero_ordre ' . $dir . ', om.date_parution ' . $dir,
            'date' => 'om.date_parution ' . $dir . ', om.numero_ordre ' . $dir,
            'titre' => 'o.titre COLLATE FRENCH_NOCASE ' . $dir,
            default => 'om.numero_ordre ' . $dir . ', om.date_parution ' . $dir,
        };
    }
}

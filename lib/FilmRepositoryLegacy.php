<?php
/**
 * Lecture et écriture des films dans la dvdthèque.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FilmRepositoryLegacy
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Colonnes triables sur la page « Ma collection ». */
    private const COLLECTION_SORT_COLUMNS = [
        'titre' => 'f.titre COLLATE FRENCH_NOCASE',
        'annee' => 'f.annee',
        'realisateur' => 'f.realisateur COLLATE FRENCH_NOCASE',
        'duree_min' => 'f.duree_min',
        'styles' => 'f.styles COLLATE FRENCH_NOCASE',
        'support_physique' => 'f.support_physique COLLATE FRENCH_NOCASE',
        'note' => 'note_max',
        'derniere_vue' => 'derniere_vue',
    ];

    /**
     * Liste complète pour la page collection (avec note et tri).
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        string $kindFilter = '',
        ?int $limit = null,
        int $offset = 0
    ): array {
        if (!isset(self::COLLECTION_SORT_COLUMNS[$sortBy])) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = self::COLLECTION_SORT_COLUMNS[$sortBy];

        $sql = 'SELECT f.*,
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = f.id) AS derniere_vue,
                (SELECT MAX(h.note) FROM historique h
                 WHERE h.film_id = f.id AND h.note IS NOT NULL AND h.note >= 1) AS note_max
             FROM films f';

        $params = [];
        $whereParts = $this->legacyCollectionWhereParts($searchQuery, $kindFilter, $params);
        if ($whereParts !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $sql .= ' ORDER BY ' . $orderExpr . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', f.titre COLLATE FRENCH_NOCASE ASC';
        }
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countCollectionFiltered(string $searchQuery = '', string $kindFilter = ''): int
    {
        $params = [];
        $whereParts = $this->legacyCollectionWhereParts($searchQuery, $kindFilter, $params);
        $sql = 'SELECT COUNT(*) FROM films f';
        if ($whereParts !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $params
     * @return list<string>
     */
    private function legacyCollectionWhereParts(string $searchQuery, string $kindFilter, array &$params): array
    {
        $whereParts = [];
        $searchWhere = self::collectionSearchWhereSql($searchQuery, $params);
        if ($searchWhere !== '') {
            $whereParts[] = $searchWhere;
        }
        $kindWhere = ContentKindFilter::sqlWhere($kindFilter, 'f.', $params);
        if ($kindWhere !== '') {
            $whereParts[] = $kindWhere;
        }

        return $whereParts;
    }

    /**
     * Filtre titre, réalisateur, acteurs, style (recherche partielle, insensible à la casse).
     *
     * @param array<string, string> $params
     */
    private static function collectionSearchWhereSql(string $searchQuery, array &$params): string
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return '';
        }

        $pattern = LikePattern::containsFragment($searchQuery);
        $params['collection_q'] = $pattern;

        $fields = [
            'f.titre',
            'f.titre_original',
            'f.realisateur',
            'f.acteur_1',
            'f.acteur_2',
            'f.acteur_3',
            'f.styles',
            'f.saga',
        ];

        $parts = [];
        foreach ($fields as $field) {
            $parts[] = 'LOWER(' . $field . ') LIKE LOWER(:collection_q) ESCAPE \'\\\'';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    /** Films + dernière vision et note (pour export). */
    public function findAllForExport(): array
    {
        $stmt = $this->db->query(
            'SELECT f.*,
                (SELECT h.date_vue FROM historique h
                 WHERE h.film_id = f.id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_vue,
                (SELECT h.note FROM historique h
                 WHERE h.film_id = f.id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_note
             FROM films f
             ORDER BY f.titre COLLATE FRENCH_NOCASE'
        );

        return $stmt->fetchAll();
    }

    /** Même liste, ordre aléatoire (pour les tirages, évite l’effet alphabétique). */
    public function findAllRandomOrder(): array
    {
        $stmt = $this->db->query(
            'SELECT f.*,
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = f.id) AS derniere_vue
             FROM films f
             ORDER BY RANDOM()'
        );
        return $stmt->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM films')->fetchColumn();
    }

    public function deleteAll(): void
    {
        $this->db->exec('DELETE FROM historique');
        $this->db->exec('DELETE FROM films');
    }

    /** Supprime un film et son historique de visions (cascade). */
    public function deleteById(int $filmId): bool
    {
        if ($filmId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM films WHERE id = ?');
        $stmt->execute([$filmId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime plusieurs films de la collection.
     *
     * @param list<int> $filmIds
     */
    public function deleteFilms(array $filmIds): int
    {
        $deleted = 0;
        foreach ($filmIds as $filmId) {
            if ($this->deleteById((int) $filmId)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM films WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByTitreAndRealisateur(string $titre, string $realisateur): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM films WHERE titre = ? AND realisateur = ?'
        );
        $stmt->execute([$titre, $realisateur]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Insère ou met à jour un film (clé : titre + réalisateur) — champs de base dvdthèque.
     *
     * @param array{titre: string, realisateur?: string, duree_min?: int, format_image?: string, format_son?: string, styles?: string} $data
     */
    public function upsert(array $data): void
    {
        $this->upsertFromExport($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string
     */
    public function createManual(array $data, string $statut): int|string
    {
        unset($statut);

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $realisateur = trim((string) ($data['realisateur'] ?? ''));
        $existing = $this->findByTitreAndRealisateur($titre, $realisateur);
        if ($existing !== null) {
            return 'Un film avec ce titre et ce réalisateur existe déjà.';
        }

        $this->upsertFromExport($data, []);
        $created = $this->findByTitreAndRealisateur($titre, $realisateur);
        if ($created === null) {
            return 'Impossible de créer le film.';
        }

        $libraryId = (int) $created['id'];
        $updateResult = $this->updateManual($libraryId, $data);
        if ($updateResult !== true) {
            return (string) $updateResult;
        }

        return $libraryId;
    }

    /**
     * Import / export complet : toutes les colonnes Moncine (aligné sur ExportCollection).
     *
     * @param array<string, mixed> $data
     */
    /**
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns Colonnes présentes dans le fichier (évite d’effacer les champs absents).
     */
    public function upsertFromExport(array $data, array $importedColumns = []): void
    {
        $existing = $this->findByTitreAndRealisateur(
            (string) $data['titre'],
            (string) ($data['realisateur'] ?? '')
        );

        $payload = $this->buildExportPayload($data, $existing, $importedColumns);

        if ($existing === null) {
            $fields = CollectionExportSchema::filmDatabaseFields();
            $columns = implode(', ', $fields);
            $placeholders = implode(', ', array_map(static fn (string $f): string => ':' . $f, $fields));
            $stmt = $this->db->prepare(
                "INSERT INTO films ($columns) VALUES ($placeholders)"
            );
            $stmt->execute($payload);

            return;
        }

        $filmId = (int) $existing['id'];
        $mergeFields = $this->resolveMergeFields($importedColumns);
        if ($mergeFields === []) {
            return;
        }

        $sets = [];
        foreach ($mergeFields as $field) {
            $sets[] = $field . ' = :' . $field;
        }

        $stmt = $this->db->prepare(
            'UPDATE films SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $payload['id'] = $filmId;
        $stmt->execute($payload);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @param list<string> $importedColumns
     * @return array<string, mixed>
     */
    private function buildExportPayload(array $data, ?array $existing, array $importedColumns): array
    {
        $importSet = $importedColumns !== [] ? array_flip($importedColumns) : null;
        $payload = [];

        foreach (CollectionExportSchema::filmDatabaseFields() as $field) {
            if ($field === 'titre') {
                $payload['titre'] = (string) $data['titre'];
                continue;
            }
            if ($field === 'realisateur') {
                $payload['realisateur'] = (string) ($data['realisateur'] ?? '');
                continue;
            }

            $hasIncoming = array_key_exists($field, $data);
            $inFile = $importSet === null || isset($importSet[$field])
                || ($field === 'tmdb_tv_kind' && isset($importSet['tmdb_media_type']));

            if ($existing === null) {
                $payload[$field] = $this->normalizeExportField($field, $data, $hasIncoming);
                continue;
            }

            if (!$inFile) {
                $payload[$field] = $this->normalizeExportField($field, $existing, true);
                continue;
            }

            $payload[$field] = $this->normalizeExportField($field, $data, $hasIncoming);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function normalizeExportField(string $field, array $source, bool $hasIncoming): mixed
    {
        if (!$hasIncoming && !array_key_exists($field, $source)) {
            return match ($field) {
                'duree_min', 'annee', 'tmdb_id', 'saga_ordre' => 0,
                default => '',
            };
        }

        return match ($field) {
            'duree_min' => (int) ($source['duree_min'] ?? 0),
            'annee' => (int) ($source['annee'] ?? 0),
            'tmdb_id' => max(0, (int) ($source['tmdb_id'] ?? 0)),
            'tmdb_media_type' => TmdbMediaType::normalize((string) ($source['tmdb_media_type'] ?? '')),
            'tmdb_tv_kind' => TmdbTvKind::normalize((string) ($source['tmdb_tv_kind'] ?? '')),
            'support_physique' => SupportPhysique::normalize((string) ($source['support_physique'] ?? '')),
            'poster_url' => SecureUrl::sanitizePosterUrl((string) ($source['poster_url'] ?? '')),
            'titre_original' => trim((string) ($source['titre_original'] ?? '')),
            'saga' => trim((string) ($source['saga'] ?? '')),
            'saga_ordre' => trim((string) ($source['saga'] ?? '')) === ''
                ? 0
                : max(0, (int) ($source['saga_ordre'] ?? 0)),
            'nationalite' => TmdbCountries::formatNationaliteList((string) ($source['nationalite'] ?? '')),
            default => (string) ($source[$field] ?? ''),
        };
    }

    /** @return list<string> Noms de saga distincts (tri français). */
    public function distinctSagas(): array
    {
        $rows = $this->db->query(
            'SELECT DISTINCT saga FROM films
             WHERE TRIM(saga) != ""
             ORDER BY saga COLLATE FRENCH_NOCASE'
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['saga'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * Sagas avec le nombre de films associés.
     *
     * @return list<array{saga: string, film_count: int}>
     */
    public function listSagasWithCounts(): array
    {
        $rows = $this->db->query(
            'SELECT saga, COUNT(*) AS film_count FROM films
             WHERE TRIM(saga) != ""
             GROUP BY saga
             ORDER BY saga COLLATE FRENCH_NOCASE'
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['saga'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'saga' => $name,
                'film_count' => (int) ($row['film_count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Films d’une saga, triés par numéro puis titre.
     *
     * @return list<array<string, mixed>>
     */
    public function findBySaga(string $saga): array
    {
        $saga = trim($saga);
        if ($saga === '') {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT f.*,
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = f.id) AS derniere_vue,
                (SELECT MAX(h.note) FROM historique h
                 WHERE h.film_id = f.id AND h.note IS NOT NULL AND h.note >= 1) AS note_max
             FROM films f
             WHERE f.saga = ?
             ORDER BY
                CASE WHEN f.saga_ordre > 0 THEN f.saga_ordre ELSE 999999 END ASC,
                f.titre COLLATE FRENCH_NOCASE ASC'
        );
        $stmt->execute([$saga]);

        return $stmt->fetchAll();
    }

    /**
     * Associe plusieurs films à une saga (numérotation 1, 2, 3… dans l’ordre des IDs fournis).
     *
     * @param list<int> $filmIds
     */
    public function assignFilmsToSaga(array $filmIds, string $saga, int $startOrder = 1): int
    {
        $saga = trim($saga);
        if ($saga === '' || $filmIds === []) {
            return 0;
        }

        $startOrder = max(1, $startOrder);
        $stmt = $this->db->prepare(
            'UPDATE films SET saga = :saga, saga_ordre = :saga_ordre WHERE id = :id'
        );

        $updated = 0;
        $ordre = $startOrder;
        foreach ($filmIds as $filmId) {
            $filmId = (int) $filmId;
            if ($filmId <= 0) {
                continue;
            }
            $stmt->execute([
                'saga' => $saga,
                'saga_ordre' => $ordre,
                'id' => $filmId,
            ]);
            if ($stmt->rowCount() > 0) {
                $updated++;
            }
            $ordre++;
        }

        return $updated;
    }

    /**
     * Renomme une saga sur tous les films concernés.
     *
     * @return array{ok: true, updated: int}|array{ok: false, error: string}
     */
    public function renameSaga(string $oldName, string $newName): array
    {
        $oldName = trim($oldName);
        $newName = trim($newName);

        if ($oldName === '') {
            return ['ok' => false, 'error' => 'Saga introuvable.'];
        }
        if ($newName === '') {
            return ['ok' => false, 'error' => 'Le nouveau nom ne peut pas être vide.'];
        }
        if ($oldName === $newName) {
            return ['ok' => true, 'updated' => 0];
        }

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM films WHERE saga = ?');
        $countStmt->execute([$oldName]);
        $filmCount = (int) $countStmt->fetchColumn();
        if ($filmCount === 0) {
            return ['ok' => false, 'error' => 'Aucun film n’utilise cette saga.'];
        }

        $stmt = $this->db->prepare('UPDATE films SET saga = :new_name WHERE saga = :old_name');
        $stmt->execute([
            'new_name' => $newName,
            'old_name' => $oldName,
        ]);

        return ['ok' => true, 'updated' => $stmt->rowCount()];
    }

    /**
     * Met à jour le support physique de plusieurs films (valeur vide = non renseigné).
     *
     * @param list<int> $filmIds
     */
    public function updateFilmsSupportPhysique(array $filmIds, string $supportKey): int
    {
        if ($filmIds === []) {
            return 0;
        }

        $supportKey = SupportPhysique::normalize($supportKey);
        $stmt = $this->db->prepare(
            'UPDATE films SET support_physique = :support_physique WHERE id = :id'
        );

        $updated = 0;
        foreach ($filmIds as $filmId) {
            $filmId = (int) $filmId;
            if ($filmId <= 0) {
                continue;
            }
            $stmt->execute([
                'support_physique' => $supportKey,
                'id' => $filmId,
            ]);
            if ($stmt->rowCount() > 0) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Identifiants de films cochés dans un formulaire de masse.
     *
     * @param array<string, mixed> $post
     * @return list<int>
     */
    public static function parseBulkFilmIds(array $post): array
    {
        $ids = [];
        foreach ((array) ($post['film_ids'] ?? []) as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public static function formatSagaOrdre(int $ordre): string
    {
        return $ordre > 0 ? (string) $ordre : '—';
    }

    /**
     * @param list<string> $importedColumns
     * @return list<string>
     */
    private function resolveMergeFields(array $importedColumns): array
    {
        if ($importedColumns === []) {
            return CollectionExportSchema::filmMergeOnConflictFields();
        }

        $importSet = array_flip($importedColumns);
        $fields = [];
        foreach (CollectionExportSchema::filmMergeOnConflictFields() as $field) {
            if (isset($importSet[$field])) {
                $fields[] = $field;
                continue;
            }
            if ($field === 'tmdb_tv_kind' && isset($importSet['tmdb_media_type'])) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Films ayant le même support physique.
     *
     * @return list<array<string, mixed>>
     */
    public function findBySupportPhysique(string $supportKey): array
    {
        if (!SupportPhysique::isValid($supportKey)) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT f.*,
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = f.id) AS derniere_vue,
                (SELECT MAX(h.note) FROM historique h
                 WHERE h.film_id = f.id AND h.note IS NOT NULL AND h.note >= 1) AS note_max
             FROM films f
             WHERE f.support_physique = ?
             ORDER BY f.titre COLLATE FRENCH_NOCASE'
        );
        $stmt->execute([$supportKey]);

        return $stmt->fetchAll();
    }

    /** @return list<string> Clés de support présentes dans la collection (triées). */
    public function distinctSupportPhysique(): array
    {
        $rows = $this->db->query(
            'SELECT DISTINCT support_physique FROM films
             WHERE TRIM(support_physique) != ""
             ORDER BY support_physique COLLATE FRENCH_NOCASE'
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $key = (string) ($row['support_physique'] ?? '');
            if (SupportPhysique::isValid($key)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    public static function formatSupport(?string $key): string
    {
        $label = SupportPhysique::label($key);

        return $label !== '' ? $label : '—';
    }

    public static function formatNationalite(?string $nationalite): string
    {
        $formatted = TmdbCountries::formatNationaliteList((string) $nationalite);

        return $formatted !== '' ? $formatted : '—';
    }

    /** @return list<string> Pays distincts (chaîne nationalite découpée par virgules). */
    public function distinctNationalites(): array
    {
        $rows = $this->db->query(
            'SELECT nationalite FROM films WHERE TRIM(nationalite) != ""'
        )->fetchAll();

        $countries = [];
        foreach ($rows as $row) {
            $label = TmdbCountries::formatNationaliteList((string) ($row['nationalite'] ?? ''));
            if ($label !== '') {
                $countries[$label] = true;
            }
        }

        $list = array_keys($countries);
        usort($list, static fn (string $a, string $b): int => strcoll($a, $b));

        return $list;
    }

    /** @return list<string> Styles distincts (séparés par virgule dans la base). */
    public function distinctStyles(): array
    {
        $rows = $this->db->query('SELECT styles FROM films WHERE styles != ""')->fetchAll();
        $styles = [];
        foreach ($rows as $row) {
            foreach (self::splitStyles((string) $row['styles']) as $style) {
                $styles[$style] = true;
            }
        }
        $list = array_keys($styles);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);
        return $list;
    }

    public function countNeedingEnrichment(bool $includeAttempted = false): int
    {
        if ($includeAttempted) {
            return (int) $this->db->query('SELECT COUNT(*) FROM films')->fetchColumn();
        }
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM films WHERE ' . self::enrichmentPendingSql()
        )->fetchColumn();
    }

    private static function enrichmentPendingSql(): string
    {
        return 'omdb_enriched_at IS NULL
            OR (
                omdb_enriched_at IS NOT NULL
                AND (poster_url IS NULL OR poster_url = "")
                AND (synopsis IS NULL OR synopsis = "")
            )';
    }

    /**
     * Films pas encore enrichis (TMDB).
     *
     * @return list<array<string, mixed>>
     */
    public function findNeedingEnrichment(int $limit = 10, bool $force = false): array
    {
        if ($force) {
            $stmt = $this->db->prepare(
                'SELECT * FROM films ORDER BY titre COLLATE FRENCH_NOCASE LIMIT ?'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM films WHERE ' . self::enrichmentPendingSql() . ' ORDER BY titre COLLATE FRENCH_NOCASE LIMIT ?'
            );
        }
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Met à jour les métadonnées d’enrichissement (TMDB).
     * Réalisateur et durée : remplis seulement s’ils manquent dans votre base.
     *
     * @param array<string, mixed> $meta
     */
    public function updateEnrichmentMetadata(int $filmId, array $meta, bool $forceReplace = false): void
    {
        $film = $this->findById($filmId);
        if ($film === null) {
            return;
        }

        $newPoster = trim((string) ($meta['poster_url'] ?? ''));
        $newSynopsis = trim((string) ($meta['synopsis'] ?? ''));
        $newDuree = (int) ($meta['duree_min'] ?? 0);
        $newAnnee = (int) ($meta['annee'] ?? 0);

        if ($forceReplace && $newPoster !== '') {
            $poster = $newPoster;
        } else {
            $poster = $newPoster !== '' ? $newPoster : (string) ($film['poster_url'] ?? '');
        }
        $poster = SecureUrl::sanitizePosterUrl($poster);
        if ($poster !== '' && PosterStorage::isRemoteUrl($poster)) {
            $local = (new PosterStorage())->cacheRemoteForOeuvre($filmId, $poster);
            if ($local !== '') {
                $poster = $local;
            }
        }

        if ($forceReplace && $newSynopsis !== '') {
            $synopsis = $newSynopsis;
        } else {
            $synopsis = $newSynopsis !== '' ? $newSynopsis : (string) ($film['synopsis'] ?? '');
        }

        $newRealisateur = trim((string) ($meta['realisateur'] ?? ''));
        if ($forceReplace && $newRealisateur !== '') {
            $realisateur = $newRealisateur;
        } elseif ($newRealisateur !== '') {
            $realisateur = $newRealisateur;
        } else {
            $realisateur = trim((string) ($film['realisateur'] ?? ''));
        }

        $acteur1 = $this->resolveActeurField($film, $meta, 'acteur_1', $forceReplace);
        $acteur2 = $this->resolveActeurField($film, $meta, 'acteur_2', $forceReplace);
        $acteur3 = $this->resolveActeurField($film, $meta, 'acteur_3', $forceReplace);

        if ($forceReplace && $newDuree > 0) {
            $duree = $newDuree;
        } else {
            $duree = (int) ($film['duree_min'] ?? 0);
            if ($duree <= 0) {
                $duree = $newDuree;
            }
        }

        if ($forceReplace && $newAnnee > 0) {
            $annee = $newAnnee;
        } else {
            $annee = (int) ($film['annee'] ?? 0);
            if ($annee <= 0) {
                $annee = $newAnnee;
            }
        }

        $incomingTmdbId = (int) ($meta['tmdb_id'] ?? 0);
        if ($incomingTmdbId > 0) {
            $tmdbId = $incomingTmdbId;
        } else {
            $tmdbId = (int) ($film['tmdb_id'] ?? 0);
        }

        $incomingMediaType = TmdbMediaType::normalize((string) ($meta['tmdb_media_type'] ?? ''));
        if ($incomingMediaType !== '') {
            $tmdbMediaType = $incomingMediaType;
        } elseif ($tmdbId > 0 && trim((string) ($film['tmdb_media_type'] ?? '')) !== '') {
            $tmdbMediaType = TmdbMediaType::normalize((string) $film['tmdb_media_type']);
        } else {
            $tmdbMediaType = '';
        }

        $incomingTvKind = TmdbTvKind::normalize((string) ($meta['tmdb_tv_kind'] ?? ''));
        if ($incomingTvKind !== '') {
            $tmdbTvKind = $incomingTvKind;
        } elseif ($tmdbId > 0 && trim((string) ($film['tmdb_tv_kind'] ?? '')) !== '') {
            $tmdbTvKind = TmdbTvKind::normalize((string) $film['tmdb_tv_kind']);
        } else {
            $tmdbTvKind = '';
        }
        if ($tmdbMediaType !== TmdbMediaType::TV && !TmdbTvKind::isMovieMetadata($tmdbTvKind)) {
            $tmdbTvKind = '';
        }

        $newTitreOriginal = trim((string) ($meta['titre_original'] ?? ''));
        if (array_key_exists('titre_original', $meta)) {
            if ($forceReplace || $newTitreOriginal !== '') {
                $titreOriginal = $newTitreOriginal;
            } else {
                $titreOriginal = trim((string) ($film['titre_original'] ?? ''));
            }
        } else {
            $titreOriginal = trim((string) ($film['titre_original'] ?? ''));
        }

        $newNationalite = trim((string) ($meta['nationalite'] ?? ''));
        if (array_key_exists('nationalite', $meta)) {
            if ($forceReplace) {
                $nationalite = $newNationalite;
            } elseif ($newNationalite !== '') {
                $nationalite = $newNationalite;
            } else {
                $nationalite = trim((string) ($film['nationalite'] ?? ''));
            }
        } else {
            $nationalite = trim((string) ($film['nationalite'] ?? ''));
        }
        $nationalite = TmdbCountries::formatNationaliteList($nationalite);

        $realisateurTmdbId = $this->resolvePersonTmdbIdField($film, $meta, 'realisateur_tmdb_id', $forceReplace);
        $acteur1TmdbId = $this->resolvePersonTmdbIdField($film, $meta, 'acteur_1_tmdb_id', $forceReplace);
        $acteur2TmdbId = $this->resolvePersonTmdbIdField($film, $meta, 'acteur_2_tmdb_id', $forceReplace);
        $acteur3TmdbId = $this->resolvePersonTmdbIdField($film, $meta, 'acteur_3_tmdb_id', $forceReplace);
        $styles = TmdbGenres::mergeStylesForEnrichment($film, $meta);

        $incomingMoncineKind = MoncineContentKind::normalize((string) ($meta['moncine_kind'] ?? ''));
        if ($incomingMoncineKind !== MoncineContentKind::FILM || $forceReplace) {
            $moncineKind = $incomingMoncineKind !== ''
                ? $incomingMoncineKind
                : MoncineContentKind::fromTmdbFields($tmdbMediaType, $tmdbTvKind);
        } else {
            $moncineKind = MoncineContentKind::normalize((string) ($film['moncine_kind'] ?? MoncineContentKind::FILM));
        }

        $stmt = $this->db->prepare(
            'UPDATE films SET
                poster_url = :poster_url,
                synopsis = :synopsis,
                realisateur = :realisateur,
                styles = :styles,
                duree_min = :duree_min,
                annee = :annee,
                tmdb_id = :tmdb_id,
                tmdb_media_type = :tmdb_media_type,
                tmdb_tv_kind = :tmdb_tv_kind,
                moncine_kind = :moncine_kind,
                titre_original = :titre_original,
                nationalite = :nationalite,
                realisateur_tmdb_id = :realisateur_tmdb_id,
                acteur_1 = :acteur_1,
                acteur_2 = :acteur_2,
                acteur_3 = :acteur_3,
                acteur_1_tmdb_id = :acteur_1_tmdb_id,
                acteur_2_tmdb_id = :acteur_2_tmdb_id,
                acteur_3_tmdb_id = :acteur_3_tmdb_id,
                omdb_enriched_at = datetime(\'now\')
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $filmId,
            'poster_url' => $poster,
            'synopsis' => $synopsis,
            'realisateur' => $realisateur,
            'styles' => $styles,
            'duree_min' => $duree,
            'annee' => $annee,
            'tmdb_id' => $tmdbId,
            'tmdb_media_type' => $tmdbMediaType,
            'tmdb_tv_kind' => $tmdbTvKind,
            'moncine_kind' => $moncineKind,
            'titre_original' => $titreOriginal,
            'nationalite' => $nationalite,
            'realisateur_tmdb_id' => $realisateurTmdbId,
            'acteur_1' => $acteur1,
            'acteur_2' => $acteur2,
            'acteur_3' => $acteur3,
            'acteur_1_tmdb_id' => $acteur1TmdbId,
            'acteur_2_tmdb_id' => $acteur2TmdbId,
            'acteur_3_tmdb_id' => $acteur3TmdbId,
        ]);
    }

    public function findByTmdbId(int $tmdbId): ?array
    {
        if ($tmdbId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM films WHERE tmdb_id = ? LIMIT 1');
        $stmt->execute([$tmdbId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $film
     * @param array<string, mixed> $meta
     */
    private function resolveActeurField(array $film, array $meta, string $key, bool $forceReplace): string
    {
        $incoming = trim((string) ($meta[$key] ?? ''));
        $current = trim((string) ($film[$key] ?? ''));
        if ($forceReplace) {
            return $incoming;
        }
        return $incoming !== '' ? $incoming : $current;
    }

    /**
     * @param array<string, mixed> $film
     * @param array<string, mixed> $meta
     */
    private function resolvePersonTmdbIdField(array $film, array $meta, string $key, bool $forceReplace): int
    {
        $incoming = (int) ($meta[$key] ?? 0);
        $current = (int) ($film[$key] ?? 0);
        if ($forceReplace) {
            return $incoming;
        }
        return $incoming > 0 ? $incoming : $current;
    }

    /**
     * Recherche de films par nom (partiel) ou ID TMDB d’une personne.
     *
     * @return list<array<string, mixed>>
     */
    public function findByPersonne(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $personId = preg_match('/^\d+$/', $query) ? (int) $query : 0;
        $like = LikePattern::containsFragment($query);
        if ($like === '') {
            return [];
        }

        // PDO SQLite : un même nom de paramètre (:x) ne doit pas être répété,
        // sinon la liaison échoue et « = 0 » matche presque toute la collection.
        $nameMatch = 'LOWER(realisateur) LIKE LOWER(:like1)
                OR LOWER(acteur_1) LIKE LOWER(:like2)
                OR LOWER(acteur_2) LIKE LOWER(:like3)
                OR LOWER(acteur_3) LIKE LOWER(:like4)';
        $params = [
            'like1' => $like,
            'like2' => $like,
            'like3' => $like,
            'like4' => $like,
        ];

        if ($personId > 0) {
            $where = '(realisateur_tmdb_id = :pid1
                    OR acteur_1_tmdb_id = :pid2
                    OR acteur_2_tmdb_id = :pid3
                    OR acteur_3_tmdb_id = :pid4)
                OR (' . $nameMatch . ')';
            $params['pid1'] = $personId;
            $params['pid2'] = $personId;
            $params['pid3'] = $personId;
            $params['pid4'] = $personId;
        } else {
            $where = $nameMatch;
        }

        $stmt = $this->db->prepare(
            'SELECT f.*,
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = f.id) AS derniere_vue
             FROM films f
             WHERE ' . $where . '
             ORDER BY f.titre COLLATE FRENCH_NOCASE'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return list<string> Noms distincts (réalisateurs + acteurs) pour suggestions. */
    public function distinctPersonnes(int $limit = 300): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT DISTINCT name FROM (
                SELECT realisateur AS name FROM films WHERE TRIM(realisateur) != ""
                UNION SELECT acteur_1 FROM films WHERE TRIM(acteur_1) != ""
                UNION SELECT acteur_2 FROM films WHERE TRIM(acteur_2) != ""
                UNION SELECT acteur_3 FROM films WHERE TRIM(acteur_3) != ""
            ) ORDER BY name COLLATE FRENCH_NOCASE LIMIT ' . $limit;
        $rows = $this->db->query($sql)->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $n = trim((string) ($row['name'] ?? ''));
            if ($n !== '') {
                $out[] = $n;
            }
        }
        return $out;
    }

    /**
     * Rôles de la personne recherchée sur ce film (réalisateur, acteur…).
     *
     * @param array<string, mixed> $film
     * @return list<string>
     */
    public static function rolesForPerson(array $film, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $personId = preg_match('/^\d+$/', $query) ? (int) $query : 0;
        $q = mb_strtolower($query, 'UTF-8');
        $roles = [];

        if (self::personFieldMatches(
            (string) ($film['realisateur'] ?? ''),
            (int) ($film['realisateur_tmdb_id'] ?? 0),
            $q,
            $personId
        )) {
            $roles[] = 'Réalisateur';
        }
        foreach (['acteur_1' => 'Acteur', 'acteur_2' => 'Acteur', 'acteur_3' => 'Acteur'] as $key => $label) {
            $idKey = $key . '_tmdb_id';
            if (self::personFieldMatches(
                (string) ($film[$key] ?? ''),
                (int) ($film[$idKey] ?? 0),
                $q,
                $personId
            )) {
                if (!in_array($label, $roles, true)) {
                    $roles[] = $label;
                }
            }
        }

        return $roles;
    }

    private static function personFieldMatches(string $name, int $tmdbPersonId, string $queryLower, int $queryPersonId): bool
    {
        if ($queryPersonId > 0 && $tmdbPersonId > 0 && $tmdbPersonId === $queryPersonId) {
            return true;
        }
        if ($name === '') {
            return false;
        }
        return mb_strpos(mb_strtolower($name, 'UTF-8'), $queryLower, 0, 'UTF-8') !== false;
    }

    public static function formatAnnee(int $annee): string
    {
        return $annee > 0 ? (string) $annee : '—';
    }

    /** Affiche la durée en « 1 h 56 » ou « 90 min ». */
    public static function formatDuree(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) {
            return $h . ' h ' . $m . ' min';
        }
        if ($h > 0) {
            return $h . ' h';
        }
        return $minutes . ' min';
    }

    /**
     * Mise à jour manuelle de la fiche (formulaire sur la page film).
     *
     * @param array{
     *   titre: string,
     *   realisateur: string,
     *   duree_min: int,
     *   annee: int,
     *   styles: string,
     *   format_image: string,
     *   format_son: string,
     *   poster_url: string,
     *   synopsis: string,
     *   tmdb_id: int,
     *   acteur_1?: string,
     *   acteur_2?: string,
     *   acteur_3?: string
     * } $data
     * @return true|string true si OK, sinon message d’erreur
     */
    public function updateManual(int $filmId, array $data): bool|string
    {
        $film = $this->findById($filmId);
        if ($film === null) {
            return 'Film introuvable.';
        }

        $titre = trim($data['titre']);
        $realisateur = trim($data['realisateur']);
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $duplicate = $this->findByTitreAndRealisateur($titre, $realisateur);
        if ($duplicate !== null && (int) $duplicate['id'] !== $filmId) {
            return 'Un autre film a déjà ce titre et ce réalisateur.';
        }

        $stmt = $this->db->prepare(
            'UPDATE films SET
                titre = :titre,
                titre_original = :titre_original,
                realisateur = :realisateur,
                duree_min = :duree_min,
                annee = :annee,
                styles = :styles,
                format_image = :format_image,
                format_son = :format_son,
                support_physique = :support_physique,
                poster_url = :poster_url,
                synopsis = :synopsis,
                tmdb_id = :tmdb_id,
                tmdb_media_type = :tmdb_media_type,
                tmdb_tv_kind = :tmdb_tv_kind,
                realisateur_tmdb_id = :realisateur_tmdb_id,
                acteur_1 = :acteur_1,
                acteur_2 = :acteur_2,
                acteur_3 = :acteur_3,
                acteur_1_tmdb_id = :acteur_1_tmdb_id,
                acteur_2_tmdb_id = :acteur_2_tmdb_id,
                acteur_3_tmdb_id = :acteur_3_tmdb_id,
                saga = :saga,
                saga_ordre = :saga_ordre,
                nationalite = :nationalite,
                moncine_kind = :moncine_kind,
                saison_numero = :saison_numero,
                saison_label = :saison_label,
                ean = :ean
             WHERE id = :id'
        );
        $saga = trim((string) ($data['saga'] ?? ''));
        $sagaOrdre = max(0, (int) ($data['saga_ordre'] ?? 0));
        if ($saga === '') {
            $sagaOrdre = 0;
        }

        $tmdbId = (int) ($data['tmdb_id'] ?? 0);
        $tmdbTypes = FilmManualEdit::resolveTmdbTypesForSave($data, $film);
        $tmdbMediaType = $tmdbTypes['media_type'];
        $tmdbTvKind = $tmdbTypes['tv_kind'];

        $poster = SecureUrl::sanitizePosterUrl((string) ($data['poster_url'] ?? ''));
        if ($poster !== '' && PosterStorage::isRemoteUrl($poster)) {
            $local = (new PosterStorage())->cacheRemoteForOeuvre($filmId, $poster);
            if ($local !== '') {
                $poster = $local;
            }
        }

        $stmt->execute([
            'id' => $filmId,
            'titre' => $titre,
            'titre_original' => trim((string) ($data['titre_original'] ?? '')),
            'realisateur' => $realisateur,
            'duree_min' => (int) ($data['duree_min'] ?? 0),
            'annee' => (int) ($data['annee'] ?? 0),
            'styles' => $data['styles'] ?? '',
            'format_image' => $data['format_image'] ?? '',
            'format_son' => $data['format_son'] ?? '',
            'support_physique' => SupportPhysique::normalize($data['support_physique'] ?? ''),
            'poster_url' => $poster,
            'synopsis' => $data['synopsis'] ?? '',
            'tmdb_id' => $tmdbId,
            'tmdb_media_type' => $tmdbMediaType,
            'tmdb_tv_kind' => $tmdbTvKind,
            'realisateur_tmdb_id' => (int) ($data['realisateur_tmdb_id'] ?? 0),
            'acteur_1' => trim((string) ($data['acteur_1'] ?? '')),
            'acteur_2' => trim((string) ($data['acteur_2'] ?? '')),
            'acteur_3' => trim((string) ($data['acteur_3'] ?? '')),
            'acteur_1_tmdb_id' => (int) ($data['acteur_1_tmdb_id'] ?? 0),
            'acteur_2_tmdb_id' => (int) ($data['acteur_2_tmdb_id'] ?? 0),
            'acteur_3_tmdb_id' => (int) ($data['acteur_3_tmdb_id'] ?? 0),
            'saga' => $saga,
            'saga_ordre' => $sagaOrdre,
            'nationalite' => TmdbCountries::formatNationaliteList((string) ($data['nationalite'] ?? '')),
            'moncine_kind' => MoncineContentKind::normalize((string) ($data['moncine_kind'] ?? '')),
            'saison_numero' => max(0, (int) ($data['saison_numero'] ?? 0)),
            'saison_label' => trim((string) ($data['saison_label'] ?? '')),
            'ean' => OeuvreEanRepository::normalizeEan((string) ($data['ean'] ?? '')),
        ]);

        return true;
    }

    public function markEnrichmentAttempt(int $filmId, bool $found): void
    {
        $stmt = $this->db->prepare(
            'UPDATE films SET omdb_enriched_at = datetime(\'now\') WHERE id = ?'
        );
        $stmt->execute([$filmId]);
    }

    /** Découpe "Action, Comédie" en liste normalisée. */
    public static function splitStyles(string $styles): array
    {
        $parts = preg_split('/[,;|\/]+/', $styles) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $s = trim($part);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }
}

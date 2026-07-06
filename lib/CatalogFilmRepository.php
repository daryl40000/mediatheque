<?php
/**
 * Lecture et écriture des films via catalogue (œuvres + bibliothèque utilisateur).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogFilmRepository
{
    private PDO $db;

    private OeuvreRepository $oeuvres;

    private BibliothequeRepository $bibliotheque;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->oeuvres = new OeuvreRepository();
        $this->bibliotheque = new BibliothequeRepository();
    }

    /** Colonnes triables sur la page « Ma collection ». */
    private const COLLECTION_SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'realisateur' => 'o.realisateur COLLATE FRENCH_NOCASE',
        'duree_min' => 'o.duree_min',
        'styles' => 'o.styles COLLATE FRENCH_NOCASE',
        'support_physique' => 'b.support_physique COLLATE FRENCH_NOCASE',
        'note' => 'note_max',
        'derniere_vue' => 'derniere_vue',
    ];

    private const LIBRARY_EXPORT_FIELDS = [
        'support_physique',
        'format_image',
        'format_son',
        'saga',
        'saga_ordre',
        'saison_numero',
        'saison_label',
        'ean',
    ];

    /**
     * Liste complète pour la page collection ou wishlist (avec note et tri).
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        string $statut = LibraryStatut::COLLECTION,
        string $kindFilter = '',
        ?int $limit = null,
        int $offset = 0
    ): array {
        if (!isset(self::COLLECTION_SORT_COLUMNS[$sortBy])) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = self::COLLECTION_SORT_COLUMNS[$sortBy];

        $sql = 'SELECT ' . CatalogSchema::selectFilmRow() . $this->collectionRatingSelectSql()
             . ' FROM ' . CatalogSchema::JOIN;

        $params = [];
        $whereParts = $this->collectionWhereParts($searchQuery, $statut, $kindFilter, $params);

        $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        $sql .= ' ORDER BY ' . $orderExpr . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
        }

        $this->appendCollectionRatingParams($params);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countCollectionFiltered(
        string $searchQuery = '',
        string $kindFilter = '',
        string $statut = LibraryStatut::COLLECTION
    ): int {
        $params = [];
        $whereParts = $this->collectionWhereParts($searchQuery, $statut, $kindFilter, $params);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ' . CatalogSchema::JOIN . ' WHERE ' . implode(' AND ', $whereParts)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $params
     * @return list<string>
     */
    private function collectionWhereParts(
        string $searchQuery,
        string $statut,
        string $kindFilter,
        array &$params
    ): array {
        [$userWhere, $params] = CatalogSchema::libraryFilter($this->foyerId(), $this->userId(), $statut);
        $whereParts = [$userWhere];

        $searchWhere = self::collectionSearchWhereSql($searchQuery, $params);
        if ($searchWhere !== '') {
            $whereParts[] = $searchWhere;
        }

        $kindWhere = ContentKindFilter::sqlWhere($kindFilter, 'o.', $params);
        if ($kindWhere !== '') {
            $whereParts[] = $kindWhere;
        }

        CatalogSchema::applyMediaDomainFilter($whereParts, $params);

        return $whereParts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllWishlist(
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        string $kindFilter = ''
    ): array {
        return $this->findAll($sortBy, $sortDir, $searchQuery, LibraryStatut::WISHLIST, $kindFilter);
    }

    /** Films + dernière vision et note (pour export), collection uniquement. */
    public function findAllForExport(): array
    {
        [$userWhere, $params] = CatalogSchema::libraryFilter($this->foyerId(), $this->userId(), LibraryStatut::COLLECTION);

        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                (SELECT h.date_vue FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_vue,
                (SELECT h.note FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_note
             FROM ' . CatalogSchema::JOIN . '
             WHERE ' . $userWhere . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY o.titre COLLATE FRENCH_NOCASE'
        );
        $params['history_user_id'] = $this->userId();
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Collection + envies pour export bibliothèque (léger).
     *
     * @return list<array<string, mixed>>
     */
    public function findAllLibraryForExport(): array
    {
        [$userWhere, $params] = CatalogSchema::libraryFilter($this->foyerId(), $this->userId(), null);

        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                (SELECT h.date_vue FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_vue,
                (SELECT h.note FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_note
             FROM ' . CatalogSchema::JOIN . '
             WHERE ' . $userWhere . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY b.statut COLLATE FRENCH_NOCASE, o.titre COLLATE FRENCH_NOCASE'
        );
        $params['history_user_id'] = $this->userId();
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countLibraryEntries(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque
             WHERE (foyer_id = ? AND statut = ?) OR (user_id = ? AND statut = ?)'
        );
        $stmt->execute([
            $this->foyerId(),
            LibraryStatut::COLLECTION,
            $this->userId(),
            LibraryStatut::WISHLIST,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /** Même liste collection, ordre aléatoire. */
    public function findAllRandomOrder(): array
    {
        [$userWhere, $params] = CatalogSchema::libraryFilter($this->foyerId(), $this->userId(), LibraryStatut::COLLECTION);

        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_vue
             FROM ' . CatalogSchema::JOIN . '
             WHERE ' . $userWhere . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY RANDOM()'
        );
        $params['history_user_id'] = $this->userId();
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function count(): int
    {
        return $this->bibliotheque->countByStatut($this->userId(), $this->foyerId(), LibraryStatut::COLLECTION);
    }

    public function countWishlist(): int
    {
        return $this->bibliotheque->countByStatut($this->userId(), $this->foyerId(), LibraryStatut::WISHLIST);
    }

    public function deleteAll(): void
    {
        $userId = $this->userId();
        $foyerId = $this->foyerId();

        $stmt = $this->db->prepare(
            'DELETE FROM historique WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        if ($foyerId > 0) {
            $stmt = $this->db->prepare(
                'DELETE FROM historique WHERE film_id IN (
                    SELECT id FROM bibliotheque WHERE foyer_id = ? AND statut = ?
                 )'
            );
            $stmt->execute([$foyerId, LibraryStatut::COLLECTION]);

            $stmt = $this->db->prepare(
                'DELETE FROM bibliotheque WHERE foyer_id = ? AND statut = ?'
            );
            $stmt->execute([$foyerId, LibraryStatut::COLLECTION]);
        }

        $stmt = $this->db->prepare(
            'DELETE FROM bibliotheque WHERE user_id = ? AND statut = ?'
        );
        $stmt->execute([$userId, LibraryStatut::WISHLIST]);
    }

    public function deleteById(int $filmId): bool
    {
        if ($filmId <= 0) {
            return false;
        }

        $userId = $this->userId();
        $foyerId = $this->foyerId();
        $item = $this->bibliotheque->findById($filmId, $userId, $foyerId);
        if ($item !== null && ($item['statut'] ?? '') === LibraryStatut::COLLECTION) {
            $this->db->prepare('DELETE FROM historique WHERE film_id = ?')->execute([$filmId]);
        } else {
            $this->db->prepare('DELETE FROM historique WHERE film_id = ? AND user_id = ?')
                ->execute([$filmId, $userId]);
        }

        return $this->bibliotheque->deleteById($filmId, $userId, $foyerId);
    }

    /**
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
        return $this->bibliotheque->findById($id, $this->userId(), $this->foyerId());
    }

    public function findByTitreAndRealisateur(string $titre, string $realisateur): ?array
    {
        $oeuvre = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
        if ($oeuvre === null) {
            return null;
        }

        $library = $this->bibliotheque->findByOeuvreId((int) $oeuvre['id'], $this->userId(), $this->foyerId());
        if ($library === null) {
            return null;
        }

        return $this->findById((int) $library['id']);
    }

    /**
     * Suggestions catalogue pour l’autocomplétion du titre (ajout film).
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalogOeuvres(string $query, int $limit = 20): array
    {
        $rows = $this->oeuvres->searchByTitrePrefix($query, $limit);
        $userId = $this->userId();
        $out = [];

        foreach ($rows as $oeuvre) {
            $oeuvreId = (int) ($oeuvre['id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }

            $library = $this->bibliotheque->findByOeuvreId($oeuvreId, $userId, $this->foyerId());
            $libraryStatut = $library !== null
                ? (string) ($library['statut'] ?? LibraryStatut::COLLECTION)
                : '';

            $out[] = [
                'id' => $oeuvreId,
                'label' => self::formatCatalogOeuvreLabel($oeuvre),
                'titre' => (string) ($oeuvre['titre'] ?? ''),
                'titre_original' => (string) ($oeuvre['titre_original'] ?? ''),
                'realisateur' => (string) ($oeuvre['realisateur'] ?? ''),
                'annee' => (int) ($oeuvre['annee'] ?? 0),
                'styles' => (string) ($oeuvre['styles'] ?? ''),
                'nationalite' => (string) ($oeuvre['nationalite'] ?? ''),
                'duree_min' => (int) ($oeuvre['duree_min'] ?? 0),
                'duree' => FilmManualEdit::dureeForInput((int) ($oeuvre['duree_min'] ?? 0)),
                'acteur_1' => (string) ($oeuvre['acteur_1'] ?? ''),
                'acteur_2' => (string) ($oeuvre['acteur_2'] ?? ''),
                'acteur_3' => (string) ($oeuvre['acteur_3'] ?? ''),
                'poster_url' => (string) ($oeuvre['poster_url'] ?? ''),
                'synopsis' => (string) ($oeuvre['synopsis'] ?? ''),
                'tmdb_id' => (int) ($oeuvre['tmdb_id'] ?? 0),
                'moncine_kind' => MoncineContentKind::normalize((string) ($oeuvre['moncine_kind'] ?? '')),
                'content_kind' => MoncineContentKind::toFormValue(
                    (string) ($oeuvre['moncine_kind'] ?? MoncineContentKind::FILM),
                    (string) ($oeuvre['tmdb_media_type'] ?? ''),
                    (string) ($oeuvre['tmdb_tv_kind'] ?? '')
                ),
                'in_library' => $library !== null,
                'library_statut' => $libraryStatut,
                'library_statut_label' => $libraryStatut !== ''
                    ? LibraryStatut::label($libraryStatut)
                    : '',
                'saga' => CatalogSchema::hasOeuvreSagaColumns()
                    ? trim((string) ($oeuvre['saga'] ?? ''))
                    : '',
                'saga_ordre' => CatalogSchema::hasOeuvreSagaColumns()
                    ? max(0, (int) ($oeuvre['saga_ordre'] ?? 0))
                    : 0,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $oeuvre
     */
    public static function formatCatalogOeuvreLabel(array $oeuvre): string
    {
        $titre = trim((string) ($oeuvre['titre'] ?? ''));
        $realisateur = trim((string) ($oeuvre['realisateur'] ?? ''));
        $annee = (int) ($oeuvre['annee'] ?? 0);

        $label = $titre;
        if ($realisateur !== '') {
            $label .= ' — ' . $realisateur;
        }
        if ($annee > 0) {
            $label .= ' (' . $annee . ')';
        }

        return $label;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns
     */
    /**
     * Import bibliothèque légère : lie une œuvre du catalogue à l’utilisateur (collection ou envies).
     *
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns
     */
    public function upsertLibraryFromExport(array $data, array $importedColumns = []): void
    {
        $oeuvreId = max(0, (int) ($data['oeuvre_id'] ?? 0));
        $libraryId = max(0, (int) ($data['bibliotheque_id'] ?? 0));
        $statut = LibraryStatut::normalize((string) ($data['statut'] ?? LibraryStatut::COLLECTION));

        // Migration : l’export contient des ID bibliothèque de l’ancienne instance — on privilégie l’ID catalogue.
        if ($oeuvreId > 0) {
            $oeuvre = $this->oeuvres->findById($oeuvreId);
            if ($oeuvre === null) {
                throw new \RuntimeException(
                    'ID catalogue ' . $oeuvreId . ' introuvable. Importez d’abord le catalogue (admin).'
                );
            }
            $library = $this->bibliotheque->findByOeuvreId($oeuvreId, $this->userId(), $this->foyerId());
            if ($library !== null) {
                $this->applyLibraryImportUpdate((int) $library['id'], $data, $importedColumns, $statut);

                return;
            }

            $payload = $this->libraryPayloadFromImport($data, $statut);
            $this->bibliotheque->insert($this->userId(), $this->foyerId(), $oeuvreId, $payload);

            return;
        }

        if ($libraryId > 0) {
            $existing = $this->findById($libraryId);
            if ($existing === null) {
                throw new \RuntimeException(
                    'Entrée bibliothèque #' . $libraryId . ' introuvable (importez d’abord le catalogue ou indiquez l’ID catalogue).'
                );
            }
            $this->applyLibraryImportUpdate($libraryId, $data, $importedColumns, $statut);

            return;
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            throw new \RuntimeException('ID catalogue ou titre obligatoire.');
        }

        $realisateur = trim((string) ($data['realisateur'] ?? ''));
        $oeuvre = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
        if ($oeuvre === null) {
            throw new \RuntimeException(
                'Aucune œuvre « ' . $titre . ' » au catalogue. Utilisez l’ID catalogue ou importez le catalogue.'
            );
        }

        $library = $this->bibliotheque->findByOeuvreId((int) $oeuvre['id'], $this->userId(), $this->foyerId());
        if ($library !== null) {
            $this->applyLibraryImportUpdate((int) $library['id'], $data, $importedColumns, $statut);

            return;
        }

        $this->bibliotheque->insert(
            $this->userId(),
            $this->foyerId(),
            (int) $oeuvre['id'],
            $this->libraryPayloadFromImport($data, $statut)
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns
     */
    private function applyLibraryImportUpdate(
        int $libraryId,
        array $data,
        array $importedColumns,
        string $statut
    ): void {
        $importSet = $importedColumns !== [] ? array_flip($importedColumns) : null;
        $update = [];

        foreach (LibraryExportSchema::libraryDatabaseFields() as $field) {
            if ($importSet !== null && !isset($importSet[$field])) {
                continue;
            }
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if ($importSet === null || isset($importSet['statut'])) {
            $update['statut'] = $statut;
        }

        if ($update !== []) {
            $this->bibliotheque->update($libraryId, $update);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function libraryPayloadFromImport(array $data, string $statut): array
    {
        $saga = trim((string) ($data['saga'] ?? ''));

        return [
            'support_physique' => SupportPhysique::normalize((string) ($data['support_physique'] ?? '')),
            'format_image' => trim((string) ($data['format_image'] ?? '')),
            'format_son' => trim((string) ($data['format_son'] ?? '')),
            'saga' => $saga,
            'saga_ordre' => $saga === ''
                ? 0
                : max(0, (int) ($data['saga_ordre'] ?? 0)),
            'saison_numero' => max(0, (int) ($data['saison_numero'] ?? 0)),
            'saison_label' => trim((string) ($data['saison_label'] ?? '')),
            'ean' => OeuvreEanRepository::normalizeEan((string) ($data['ean'] ?? '')),
            'statut' => $statut,
        ];
    }

    public function upsertFromExport(array $data, array $importedColumns = []): void
    {
        $existing = $this->findByTitreAndRealisateur(
            (string) $data['titre'],
            (string) ($data['realisateur'] ?? '')
        );
        $oeuvreExisting = null;
        if ($existing === null) {
            $oeuvreExisting = $this->oeuvres->findByTitreAndRealisateur(
                (string) $data['titre'],
                (string) ($data['realisateur'] ?? '')
            );
        }

        $mergeSource = $existing ?? $oeuvreExisting;
        $payload = $this->buildExportPayload($data, $mergeSource, $importedColumns);
        [$oeuvrePayload, $libraryPayload] = $this->splitCatalogPayload($payload, $data);

        if ($existing === null && $oeuvreExisting === null) {
            $oeuvreId = $this->insertOeuvreFromImport($oeuvrePayload, $data);
            $this->cacheOeuvrePosterIfRemote($oeuvreId, (string) ($oeuvrePayload['poster_url'] ?? ''));
            $this->bibliotheque->insert($this->userId(), $this->foyerId(), $oeuvreId, $libraryPayload);

            return;
        }

        if ($existing === null && $oeuvreExisting !== null) {
            $oeuvreId = (int) $oeuvreExisting['id'];
            $oeuvreMerge = $this->resolveOeuvreMergeFields($importedColumns);
            if ($oeuvreMerge !== []) {
                $this->oeuvres->update($oeuvreId, $oeuvrePayload, $oeuvreMerge);
            }
            $this->cacheOeuvrePosterIfRemote($oeuvreId, (string) ($oeuvrePayload['poster_url'] ?? ''));
            $this->bibliotheque->insert($this->userId(), $this->foyerId(), $oeuvreId, $libraryPayload);

            return;
        }

        $libraryId = (int) $existing['id'];
        $oeuvreId = (int) $existing['oeuvre_id'];

        $oeuvreMerge = $this->resolveOeuvreMergeFields($importedColumns);
        if ($oeuvreMerge !== []) {
            $this->oeuvres->update($oeuvreId, $oeuvrePayload, $oeuvreMerge);
            $this->cacheOeuvrePosterIfRemote($oeuvreId, (string) ($oeuvrePayload['poster_url'] ?? ''));
        }

        $libraryMerge = $this->resolveLibraryMergeFields($importedColumns);
        $libraryUpdate = [];
        foreach ($libraryMerge as $field) {
            $libraryUpdate[$field] = $libraryPayload[$field];
        }
        if ($importedColumns === [] || array_key_exists('statut', $data)) {
            $libraryUpdate['statut'] = $libraryPayload['statut'];
        }
        if ($libraryUpdate !== []) {
            $this->bibliotheque->update($libraryId, $libraryUpdate);
        }
    }

    /** @return list<string> */
    public function distinctSagas(): array
    {
        if (CatalogSchema::hasOeuvreSagaColumns()) {
            $stmt = $this->db->prepare(
                'SELECT DISTINCT o.saga FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(o.saga) != ""
                 ORDER BY o.saga COLLATE FRENCH_NOCASE'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT DISTINCT b.saga FROM bibliotheque b
                 WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(b.saga) != ""
                 ORDER BY b.saga COLLATE FRENCH_NOCASE'
            );
        }
        $stmt->execute([$this->foyerId(), LibraryStatut::COLLECTION]);
        $rows = $stmt->fetchAll();

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
     * Sagas déjà utilisées dans le catalogue (autocomplétion des formulaires).
     *
     * @return list<string>
     */
    public function listKnownSagas(int $limit = 120): array
    {
        if (!CatalogSchema::hasOeuvreSagaColumns()) {
            return $this->distinctSagas();
        }

        $limit = max(1, min($limit, 300));
        $stmt = $this->db->query(
            'SELECT saga FROM oeuvres WHERE TRIM(saga) != \'\'
             ORDER BY saga COLLATE FRENCH_NOCASE ASC'
        );
        if ($stmt === false) {
            return [];
        }

        $known = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['saga'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (!isset($known[$key])) {
                $known[$key] = $name;
            }
        }

        $names = array_values($known);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return array_slice($names, 0, $limit);
    }

    /**
     * @return list<array{saga: string, film_count: int}>
     */
    public function listSagasWithCounts(): array
    {
        if (CatalogSchema::hasOeuvreSagaColumns()) {
            $stmt = $this->db->prepare(
                'SELECT o.saga, COUNT(*) AS film_count FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(o.saga) != ""
                 GROUP BY o.saga
                 ORDER BY o.saga COLLATE FRENCH_NOCASE'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT b.saga, COUNT(*) AS film_count FROM bibliotheque b
                 WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(b.saga) != ""
                 GROUP BY b.saga
                 ORDER BY b.saga COLLATE FRENCH_NOCASE'
            );
        }
        $stmt->execute([$this->foyerId(), LibraryStatut::COLLECTION]);
        $rows = $stmt->fetchAll();

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
     * @return list<array<string, mixed>>
     */
    public function findBySaga(string $saga): array
    {
        $saga = trim($saga);
        if ($saga === '') {
            return [];
        }

        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
            'saga' => $saga,
        ];
        $sagaFilter = CatalogSchema::hasOeuvreSagaColumns()
            ? 'o.saga = :saga'
            : 'b.saga = :saga';
        $orderBy = CatalogSchema::hasOeuvreSagaColumns()
            ? 'CASE WHEN o.saga_ordre > 0 THEN o.saga_ordre ELSE 999999 END ASC'
            : 'CASE WHEN b.saga_ordre > 0 THEN b.saga_ordre ELSE 999999 END ASC';
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . $this->collectionRatingSelectSql() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id
               AND b.statut = :catalog_statut
               AND ' . $sagaFilter
            . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY
                ' . $orderBy . ',
                o.titre COLLATE FRENCH_NOCASE ASC'
        );
        $this->appendCollectionRatingParams($params);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Tous les films catalogue d’une saga (tri saga_ordre, titre), hors œuvre exclue.
     *
     * @return list<array{oeuvre_id: int, titre: string, annee: int, poster_url: string|null, saga_ordre: int}>
     */
    public function listCatalogBySaga(string $saga, int $excludeOeuvreId = 0): array
    {
        $saga = trim($saga);
        if ($saga === '' || !CatalogSchema::hasOeuvreSagaColumns()) {
            return [];
        }

        $params = [
            'saga' => $saga,
            'domain' => MediaDomain::FILM,
        ];
        $sql = 'SELECT o.id AS oeuvre_id, o.titre, o.annee, o.poster_url, o.saga_ordre
                FROM oeuvres o
                WHERE o.media_domain = :domain
                  AND o.saga = :saga';
        if ($excludeOeuvreId > 0) {
            $sql .= ' AND o.id != :exclude_id';
            $params['exclude_id'] = $excludeOeuvreId;
        }
        $sql .= ' ORDER BY
                    CASE WHEN o.saga_ordre > 0 THEN o.saga_ordre ELSE 999999 END ASC,
                    o.titre COLLATE FRENCH_NOCASE ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'oeuvre_id' => (int) ($row['oeuvre_id'] ?? 0),
                'titre' => (string) ($row['titre'] ?? ''),
                'annee' => (int) ($row['annee'] ?? 0),
                'poster_url' => $row['poster_url'] ?? null,
                'saga_ordre' => (int) ($row['saga_ordre'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $filmIds
     */
    public function assignFilmsToSaga(array $filmIds, string $saga, int $startOrder = 1): int
    {
        $saga = trim($saga);
        if ($saga === '' || $filmIds === []) {
            return 0;
        }

        $startOrder = max(1, $startOrder);
        $lookup = $this->db->prepare(
            'SELECT oeuvre_id FROM bibliotheque
             WHERE id = :id AND foyer_id = :foyer_id AND statut = :statut'
        );
        $updateCatalog = CatalogSchema::hasOeuvreSagaColumns()
            ? $this->db->prepare(
                'UPDATE oeuvres SET saga = :saga, saga_ordre = :saga_ordre, updated_at = datetime(\'now\')
                 WHERE id = :oeuvre_id'
            )
            : null;
        $stmt = $this->db->prepare(
            'UPDATE bibliotheque SET saga = :saga, saga_ordre = :saga_ordre
             WHERE id = :id AND foyer_id = :foyer_id AND statut = :statut'
        );

        $updated = 0;
        $ordre = $startOrder;
        foreach ($filmIds as $filmId) {
            $filmId = (int) $filmId;
            if ($filmId <= 0) {
                continue;
            }

            $oeuvreId = 0;
            if ($updateCatalog !== null) {
                $lookup->execute([
                    'id' => $filmId,
                    'foyer_id' => $this->foyerId(),
                    'statut' => LibraryStatut::COLLECTION,
                ]);
                $oeuvreId = (int) $lookup->fetchColumn();
            }

            $stmt->execute([
                'saga' => $saga,
                'saga_ordre' => $ordre,
                'id' => $filmId,
                'foyer_id' => $this->foyerId(),
                'statut' => LibraryStatut::COLLECTION,
            ]);
            if ($stmt->rowCount() > 0) {
                $updated++;
            }

            if ($updateCatalog !== null && $oeuvreId > 0) {
                $updateCatalog->execute([
                    'saga' => $saga,
                    'saga_ordre' => $ordre,
                    'oeuvre_id' => $oeuvreId,
                ]);
            }

            $ordre++;
        }

        return $updated;
    }

    /**
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

        if (CatalogSchema::hasOeuvreSagaColumns()) {
            $countStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = ? AND b.statut = ? AND o.saga = ?'
            );
            $countStmt->execute([$this->foyerId(), LibraryStatut::COLLECTION, $oldName]);
            $filmCount = (int) $countStmt->fetchColumn();
            if ($filmCount === 0) {
                return ['ok' => false, 'error' => 'Aucun film n’utilise cette saga.'];
            }

            $catalogStmt = $this->db->prepare(
                'UPDATE oeuvres SET saga = :new_name, updated_at = datetime(\'now\')
                 WHERE saga = :old_name'
            );
            $catalogStmt->execute([
                'new_name' => $newName,
                'old_name' => $oldName,
            ]);

            $stmt = $this->db->prepare(
                'UPDATE bibliotheque SET saga = :new_name
                 WHERE foyer_id = :foyer_id AND statut = :statut AND saga = :old_name'
            );
            $stmt->execute([
                'new_name' => $newName,
                'foyer_id' => $this->foyerId(),
                'statut' => LibraryStatut::COLLECTION,
                'old_name' => $oldName,
            ]);

            return ['ok' => true, 'updated' => $stmt->rowCount()];
        }

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque
             WHERE foyer_id = ? AND statut = ? AND saga = ?'
        );
        $countStmt->execute([$this->foyerId(), LibraryStatut::COLLECTION, $oldName]);
        $filmCount = (int) $countStmt->fetchColumn();
        if ($filmCount === 0) {
            return ['ok' => false, 'error' => 'Aucun film n’utilise cette saga.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE bibliotheque SET saga = :new_name
             WHERE foyer_id = :foyer_id AND statut = :statut AND saga = :old_name'
        );
        $stmt->execute([
            'new_name' => $newName,
            'foyer_id' => $this->foyerId(),
            'statut' => LibraryStatut::COLLECTION,
            'old_name' => $oldName,
        ]);

        return ['ok' => true, 'updated' => $stmt->rowCount()];
    }

    /**
     * @param list<int> $filmIds
     */
    public function updateFilmsSupportPhysique(array $filmIds, string $supportKey): int
    {
        if ($filmIds === []) {
            return 0;
        }

        $supportKey = SupportPhysique::normalize($supportKey);
        $stmt = $this->db->prepare(
            'UPDATE bibliotheque SET support_physique = :support_physique
             WHERE id = :id AND foyer_id = :foyer_id'
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
                'foyer_id' => $this->foyerId(),
            ]);
            if ($stmt->rowCount() > 0) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $post
     * @return list<int>
     */
    public static function parseBulkFilmIds(array $post): array
    {
        return FilmRepository::parseBulkFilmIds($post);
    }

    public static function formatSagaOrdre(int $ordre): string
    {
        return FilmRepository::formatSagaOrdre($ordre);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findBySupportPhysique(string $supportKey): array
    {
        if (!SupportPhysique::isValid($supportKey)) {
            return [];
        }

        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
            'support' => $supportKey,
        ];
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . $this->collectionRatingSelectSql() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id
               AND b.statut = :catalog_statut
               AND b.support_physique = :support'
            . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY o.titre COLLATE FRENCH_NOCASE'
        );
        $this->appendCollectionRatingParams($params);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return list<string> */
    public function distinctSupportPhysique(): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT b.support_physique FROM bibliotheque b
             WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(b.support_physique) != ""
             ORDER BY b.support_physique COLLATE FRENCH_NOCASE'
        );
        $stmt->execute([$this->foyerId(), LibraryStatut::COLLECTION]);
        $rows = $stmt->fetchAll();

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
        return FilmRepository::formatSupport($key);
    }

    public static function formatNationalite(?string $nationalite): string
    {
        return FilmRepository::formatNationalite($nationalite);
    }

    /** @return list<string> */
    public function distinctNationalites(): array
    {
        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
        ];
        $stmt = $this->db->prepare(
            'SELECT o.nationalite FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut AND TRIM(o.nationalite) != ""'
            . CatalogSchema::sqlMediaDomainAnd('o', $params)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

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

    /** @return list<string> */
    public function distinctStyles(): array
    {
        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
        ];
        $stmt = $this->db->prepare(
            'SELECT o.styles FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut AND o.styles != ""'
            . CatalogSchema::sqlMediaDomainAnd('o', $params)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

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
            $params = [
                'catalog_foyer_id' => $this->foyerId(),
                'catalog_statut' => LibraryStatut::COLLECTION,
            ];
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM ' . CatalogSchema::JOIN . '
                 WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut'
                . CatalogSchema::sqlMediaDomainAnd('o', $params)
            );
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
        ];
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut AND ' . self::enrichmentPendingSql('o')
            . CatalogSchema::sqlMediaDomainAnd('o', $params)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findNeedingEnrichment(int $limit = 10, bool $force = false): array
    {
        $limit = max(1, $limit);
        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
            'lim' => $limit,
        ];
        $domainSql = CatalogSchema::sqlMediaDomainAnd('o', $params);
        if ($force) {
            $stmt = $this->db->prepare(
                'SELECT ' . CatalogSchema::selectFilmRow() . '
                 FROM ' . CatalogSchema::JOIN . '
                 WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut'
                . $domainSql . '
                 ORDER BY o.titre COLLATE FRENCH_NOCASE
                 LIMIT :lim'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT ' . CatalogSchema::selectFilmRow() . '
                 FROM ' . CatalogSchema::JOIN . '
                 WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut
                   AND ' . self::enrichmentPendingSql('o')
                . $domainSql . '
                 ORDER BY o.titre COLLATE FRENCH_NOCASE
                 LIMIT :lim'
            );
        }
        $stmt->bindValue('catalog_foyer_id', $params['catalog_foyer_id'], PDO::PARAM_INT);
        $stmt->bindValue('catalog_statut', $params['catalog_statut']);
        if (isset($params['catalog_media_domain'])) {
            $stmt->bindValue('catalog_media_domain', $params['catalog_media_domain']);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function updateEnrichmentMetadata(int $filmId, array $meta, bool $forceReplace = false): void
    {
        $film = $this->findById($filmId);
        if ($film === null) {
            return;
        }

        $this->updateOeuvreEnrichmentMetadata((int) $film['oeuvre_id'], $meta, $forceReplace, $film);
    }

    /**
     * Met à jour les métadonnées TMDB d’une œuvre du catalogue (toutes bibliothèques liées).
     *
     * @param array<string, mixed> $meta
     * @param array<string, mixed>|null $filmRow Ligne œuvre (ou jointe) pour fusion ; sinon chargée par ID.
     */
    public function updateOeuvreEnrichmentMetadata(
        int $oeuvreId,
        array $meta,
        bool $forceReplace = false,
        ?array $filmRow = null
    ): void {
        if ($oeuvreId <= 0) {
            return;
        }

        $film = $filmRow ?? $this->oeuvres->findById($oeuvreId);
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
        $poster = $this->resolvePosterForOeuvre($oeuvreId, $poster);

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

        $titre = trim((string) ($film['titre'] ?? ''));
        if (array_key_exists('titre', $meta)) {
            $newTitre = trim((string) ($meta['titre'] ?? ''));
            if ($forceReplace && $newTitre !== '') {
                $titre = $newTitre;
            }
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
            'UPDATE oeuvres SET
                titre = :titre,
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
                omdb_enriched_at = datetime(\'now\'),
                updated_at = datetime(\'now\')
             WHERE id = :oeuvre_id'
        );
        $stmt->execute([
            'oeuvre_id' => $oeuvreId,
            'titre' => $titre,
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

        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_user_id' => $this->userId(),
            'tmdb_id' => $tmdbId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
        ];
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE (
                    (b.foyer_id = :catalog_foyer_id AND b.statut = :collection)
                    OR (b.user_id = :catalog_user_id AND b.statut = :wishlist)
                 ) AND o.tmdb_id = :tmdb_id'
            . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY CASE WHEN b.statut = :collection THEN 0 ELSE 1 END, b.id ASC
             LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Films du catalogue partagé où la personne apparaît (réalisateur ou acteur),
     * avec indication collection / envies / absent de la bibliothèque.
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

        $nameMatch = 'LOWER(o.realisateur) LIKE LOWER(:like1)
                OR LOWER(o.acteur_1) LIKE LOWER(:like2)
                OR LOWER(o.acteur_2) LIKE LOWER(:like3)
                OR LOWER(o.acteur_3) LIKE LOWER(:like4)';
        $params = [
            'like1' => $like,
            'like2' => $like,
            'like3' => $like,
            'like4' => $like,
            'foyer_id' => $this->foyerId(),
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'user_id' => $this->userId(),
            'history_user_id' => $this->userId(),
        ];

        if ($personId > 0) {
            $where = '(o.realisateur_tmdb_id = :pid1
                    OR o.acteur_1_tmdb_id = :pid2
                    OR o.acteur_2_tmdb_id = :pid3
                    OR o.acteur_3_tmdb_id = :pid4)
                OR (' . $nameMatch . ')';
            $params['pid1'] = $personId;
            $params['pid2'] = $personId;
            $params['pid3'] = $personId;
            $params['pid4'] = $personId;
        } else {
            $where = $nameMatch;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . $this->selectPersonSearchRow() . ',
                (SELECT MAX(h.date_vue) FROM historique h
                 WHERE h.film_id = b_coll.id AND h.user_id = :history_user_id) AS derniere_vue
             FROM oeuvres o
             LEFT JOIN bibliotheque b_coll ON b_coll.oeuvre_id = o.id
                 AND b_coll.foyer_id = :foyer_id AND b_coll.statut = :collection
             LEFT JOIN bibliotheque b_wish ON b_wish.oeuvre_id = o.id
                 AND b_wish.user_id = :user_id AND b_wish.statut = :wishlist
             WHERE (' . $where . ')' . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY o.titre COLLATE FRENCH_NOCASE'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return list<string> */
    public function distinctPersonnes(int $limit = 300): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT DISTINCT name FROM (
                SELECT realisateur AS name FROM oeuvres WHERE TRIM(realisateur) != ""
                UNION SELECT acteur_1 FROM oeuvres WHERE TRIM(acteur_1) != ""
                UNION SELECT acteur_2 FROM oeuvres WHERE TRIM(acteur_2) != ""
                UNION SELECT acteur_3 FROM oeuvres WHERE TRIM(acteur_3) != ""
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

    private function selectPersonSearchRow(): string
    {
        $parts = [
            'o.id AS oeuvre_id',
            'COALESCE(b_coll.id, b_wish.id, 0) AS id',
            'COALESCE(b_coll.user_id, b_wish.user_id, 0) AS user_id',
            'COALESCE(b_coll.foyer_id, b_wish.foyer_id, 0) AS foyer_id',
            'COALESCE(b_coll.statut, b_wish.statut, \'\') AS statut',
            'CASE WHEN b_coll.id IS NOT NULL THEN \'collection\'
                  WHEN b_wish.id IS NOT NULL THEN \'wishlist\'
                  ELSE \'none\' END AS library_presence',
        ];
        foreach (CatalogSchema::LIBRARY_FIELDS as $field) {
            $parts[] = 'COALESCE(b_coll.' . $field . ', b_wish.' . $field . ', \'\') AS ' . $field;
        }
        foreach (CatalogSchema::OEUVRE_FIELDS as $field) {
            $parts[] = 'o.' . $field;
        }
        $parts[] = 'COALESCE(b_coll.created_at, b_wish.created_at, \'\') AS created_at';

        return implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $film
     * @return list<string>
     */
    public static function rolesForPerson(array $film, string $query): array
    {
        return FilmRepository::rolesForPerson($film, $query);
    }

    public static function formatAnnee(int $annee): string
    {
        return FilmRepository::formatAnnee($annee);
    }

    public static function formatDuree(int $minutes): string
    {
        return FilmRepository::formatDuree($minutes);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    /**
     * Met à jour uniquement l’exemplaire personnel (bibliothèque), pas le catalogue partagé.
     *
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateManual(int $filmId, array $data): bool|string
    {
        $film = $this->findById($filmId);
        if ($film === null) {
            return 'Film introuvable.';
        }

        $saga = trim((string) ($data['saga'] ?? ''));
        $sagaOrdre = max(0, (int) ($data['saga_ordre'] ?? 0));
        if ($saga === '') {
            $oeuvre = $this->oeuvres->findById((int) ($film['oeuvre_id'] ?? 0));
            if ($oeuvre !== null) {
                [$saga, $sagaOrdre] = $this->resolveLibrarySagaFromOeuvre($oeuvre, $data);
            }
        }
        if ($saga === '') {
            $sagaOrdre = 0;
        }

        $moncineKind = MoncineContentKind::normalize((string) ($film['moncine_kind'] ?? ''));
        $saisonNumero = max(0, (int) ($data['saison_numero'] ?? 0));
        $saisonLabel = trim((string) ($data['saison_label'] ?? ''));
        if ($moncineKind !== MoncineContentKind::SERIE) {
            $saisonNumero = 0;
            $saisonLabel = '';
        }

        $this->bibliotheque->update($filmId, [
            'support_physique' => SupportPhysique::normalize($data['support_physique'] ?? ''),
            'format_image' => trim((string) ($data['format_image'] ?? '')),
            'format_son' => trim((string) ($data['format_son'] ?? '')),
            'saga' => $saga,
            'saga_ordre' => $sagaOrdre,
            'saison_numero' => $saisonNumero,
            'saison_label' => $saisonLabel,
            'ean' => OeuvreEanRepository::normalizeEan((string) ($data['ean'] ?? '')),
        ]);

        return true;
    }

    public function markEnrichmentAttempt(int $filmId, bool $found): void
    {
        $film = $this->findById($filmId);
        if ($film === null) {
            return;
        }

        $this->markOeuvreEnrichmentAttempt((int) $film['oeuvre_id']);
    }

    public function markOeuvreEnrichmentAttempt(int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvres SET omdb_enriched_at = datetime(\'now\'), updated_at = datetime(\'now\') WHERE id = ?'
        )->execute([$oeuvreId]);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateOeuvreManual(int $oeuvreId, array $data): bool|string
    {
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        $film = $this->oeuvres->findById($oeuvreId);
        if ($film === null) {
            return 'Œuvre introuvable.';
        }

        $titre = trim($data['titre']);
        $realisateur = trim($data['realisateur']);
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $existing = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
        if ($existing !== null && (int) ($existing['id'] ?? 0) !== $oeuvreId) {
            return 'Une autre œuvre a déjà ce titre et ce réalisateur.';
        }

        $tmdbId = (int) ($data['tmdb_id'] ?? 0);
        $tmdbTypes = FilmManualEdit::resolveTmdbTypesForSave($data, $film);
        $oeuvrePayload = [
            'titre' => $titre,
            'titre_original' => trim((string) ($data['titre_original'] ?? '')),
            'realisateur' => $realisateur,
            'duree_min' => (int) ($data['duree_min'] ?? 0),
            'annee' => (int) ($data['annee'] ?? 0),
            'styles' => $data['styles'] ?? '',
            'poster_url' => $this->resolvePosterForOeuvre($oeuvreId, (string) ($data['poster_url'] ?? '')),
            'synopsis' => $data['synopsis'] ?? '',
            'tmdb_id' => $tmdbId,
            'tmdb_media_type' => $tmdbTypes['media_type'],
            'tmdb_tv_kind' => $tmdbTypes['tv_kind'],
            'realisateur_tmdb_id' => (int) ($data['realisateur_tmdb_id'] ?? 0),
            'acteur_1' => trim((string) ($data['acteur_1'] ?? '')),
            'acteur_2' => trim((string) ($data['acteur_2'] ?? '')),
            'acteur_3' => trim((string) ($data['acteur_3'] ?? '')),
            'acteur_1_tmdb_id' => (int) ($data['acteur_1_tmdb_id'] ?? 0),
            'acteur_2_tmdb_id' => (int) ($data['acteur_2_tmdb_id'] ?? 0),
            'acteur_3_tmdb_id' => (int) ($data['acteur_3_tmdb_id'] ?? 0),
            'nationalite' => TmdbCountries::formatNationaliteList((string) ($data['nationalite'] ?? '')),
            'moncine_kind' => MoncineContentKind::normalize((string) ($data['moncine_kind'] ?? '')),
        ];
        $this->oeuvres->update($oeuvreId, $oeuvrePayload, array_keys($oeuvrePayload));

        return true;
    }

    /**
     * Crée un film dans la bibliothèque (collection ou wishlist).
     *
     * @param array<string, mixed> $data Champs formulaire (comme updateManual)
     * @return int|string ID bibliothèque si OK, sinon message d’erreur
     */
    /**
     * Ajoute une œuvre déjà au catalogue à la bibliothèque (sans formulaire détaillé).
     *
     * @return int|string ID bibliothèque ou message d’erreur
     */
    public function addFromCatalogOeuvre(int $oeuvreId, string $statut): int|string
    {
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        $oeuvre = $this->oeuvres->findById($oeuvreId);
        if ($oeuvre === null) {
            return 'Cette œuvre n’existe plus dans le catalogue.';
        }

        return $this->attachOeuvreToLibrary($oeuvreId, [
            'oeuvre_id' => $oeuvreId,
            'titre' => (string) ($oeuvre['titre'] ?? ''),
            'realisateur' => (string) ($oeuvre['realisateur'] ?? ''),
            'annee' => (int) ($oeuvre['annee'] ?? 0),
            'moncine_kind' => (string) ($oeuvre['moncine_kind'] ?? ''),
        ], LibraryStatut::normalize($statut));
    }

    public function createManual(array $data, string $statut): int|string
    {
        $statut = LibraryStatut::normalize($statut);
        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $oeuvreId = max(0, (int) ($data['oeuvre_id'] ?? 0));
        if ($oeuvreId > 0) {
            $oeuvre = $this->oeuvres->findById($oeuvreId);
            if ($oeuvre === null) {
                return 'Cette œuvre n’existe plus dans le catalogue.';
            }

            return $this->attachOeuvreToLibrary($oeuvreId, $data, $statut);
        }

        $realisateur = trim((string) ($data['realisateur'] ?? ''));
        $existing = $this->findByTitreAndRealisateur($titre, $realisateur);
        if ($existing !== null) {
            return $this->attachOeuvreToLibrary((int) $existing['oeuvre_id'], $data, $statut);
        }

        $oeuvre = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
        if ($oeuvre !== null) {
            return $this->attachOeuvreToLibrary((int) $oeuvre['id'], $data, $statut);
        }

        if (!UserContext::canManageCatalog()) {
            return 'Cette œuvre n’est pas encore dans le catalogue Moncine. '
                . 'Proposez-la d’abord via « Proposer au catalogue », '
                . 'puis ajoutez-la à vos films une fois la proposition acceptée.';
        }

        $data['statut'] = $statut;
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
     * Relie une œuvre du catalogue à la bibliothèque de l’utilisateur (nouvelle entrée ou changement de statut).
     *
     * @param array<string, mixed> $data
     * @return int|string ID bibliothèque ou message d’erreur
     */
    private function attachOeuvreToLibrary(int $oeuvreId, array $data, string $statut): int|string
    {
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        $oeuvre = $this->oeuvres->findById($oeuvreId);
        if ($oeuvre === null) {
            return 'Œuvre introuvable dans le catalogue.';
        }

        $library = $this->bibliotheque->findByOeuvreId($oeuvreId, $this->userId(), $this->foyerId());
        if ($library !== null) {
            $libraryId = (int) $library['id'];
            $currentStatut = (string) ($library['statut'] ?? LibraryStatut::COLLECTION);
            if ($currentStatut === $statut) {
                return 'Ce film existe déjà dans « ' . LibraryStatut::label($statut) . ' ».';
            }

            $updateResult = $this->updateManual($libraryId, $data);
            if ($updateResult !== true) {
                return (string) $updateResult;
            }
            $update = ['statut' => $statut];
            if ($statut === LibraryStatut::COLLECTION) {
                $update['foyer_id'] = $this->foyerId();
            } else {
                $update['foyer_id'] = null;
            }
            $this->bibliotheque->update($libraryId, $update);

            return $libraryId;
        }

        $libraryPayload = [
            'support_physique' => SupportPhysique::normalize((string) ($data['support_physique'] ?? '')),
            'format_image' => trim((string) ($data['format_image'] ?? '')),
            'format_son' => trim((string) ($data['format_son'] ?? '')),
            'saison_numero' => max(0, (int) ($data['saison_numero'] ?? 0)),
            'saison_label' => trim((string) ($data['saison_label'] ?? '')),
            'ean' => OeuvreEanRepository::normalizeEan((string) ($data['ean'] ?? '')),
            'statut' => $statut,
        ];
        [$libraryPayload['saga'], $libraryPayload['saga_ordre']] = $this->resolveLibrarySagaFromOeuvre($oeuvre, $data);

        $libraryId = $this->bibliotheque->insert($this->userId(), $this->foyerId(), $oeuvreId, $libraryPayload);
        $updateResult = $this->updateManual($libraryId, $data);
        if ($updateResult !== true) {
            $this->bibliotheque->deleteById($libraryId, $this->userId(), $this->foyerId());

            return (string) $updateResult;
        }

        return $libraryId;
    }

    public function promoteToCollection(
        int $libraryId,
        string $supportKey = '',
        string $ean = '',
        ?int $wishlistTargetId = null
    ): bool {
        return $this->bibliotheque->promoteToCollection(
            $libraryId,
            $this->userId(),
            $this->foyerId(),
            $supportKey,
            $ean,
            $wishlistTargetId
        );
    }

    /** @return list<string> */
    public static function splitStyles(string $styles): array
    {
        return FilmRepository::splitStyles($styles);
    }

    /**
     * @param array<string, string> $params
     */
    private static function collectionSearchWhereSql(string $searchQuery, array &$params): string
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return '';
        }

        $pattern = SearchMatch::foldedContainsPattern($searchQuery);
        $params['collection_q'] = $pattern;

        $fields = [
            'o.titre',
            'o.titre_original',
            'o.realisateur',
            'o.acteur_1',
            'o.acteur_2',
            'o.acteur_3',
            'o.styles',
            'b.saga',
        ];
        if (CatalogSchema::hasOeuvreSagaColumns()) {
            $fields[] = 'o.saga';
        }

        $parts = [];
        foreach ($fields as $field) {
            $parts[] = 'fold_search(' . $field . ') LIKE :collection_q ESCAPE \'\\\'';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    private function collectionRatingSelectSql(): string
    {
        $noteWhere = RessentiNote::sqlValidNote('h');

        return ',
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_vue,
                (SELECT MAX(h.note) FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id AND ' . $noteWhere . ') AS note_max';
    }

    /** @param array<string, int|string|float|null> $params */
    private function appendCollectionRatingParams(array &$params): void
    {
        $params['history_user_id'] = $this->userId();
    }

    private function userId(): int
    {
        return UserContext::currentUserId();
    }

    private function foyerId(): int
    {
        return UserContext::currentFoyerId();
    }

    private static function enrichmentPendingSql(string $alias): string
    {
        $p = $alias . '.';

        return $p . 'omdb_enriched_at IS NULL
            OR (
                ' . $p . 'omdb_enriched_at IS NOT NULL
                AND (' . $p . 'poster_url IS NULL OR ' . $p . 'poster_url = "")
                AND (' . $p . 'synopsis IS NULL OR ' . $p . 'synopsis = "")
            )';
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

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function splitCatalogPayload(array $payload, array $data): array
    {
        $oeuvre = [];
        foreach (CatalogSchema::OEUVRE_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $oeuvre[$field] = $payload[$field];
            }
        }

        $library = [
            'support_physique' => $payload['support_physique'] ?? '',
            'format_image' => $payload['format_image'] ?? '',
            'format_son' => $payload['format_son'] ?? '',
            'saga' => $payload['saga'] ?? '',
            'saga_ordre' => $payload['saga_ordre'] ?? 0,
            'saison_numero' => $payload['saison_numero'] ?? 0,
            'saison_label' => $payload['saison_label'] ?? '',
            'ean' => OeuvreEanRepository::normalizeEan((string) ($payload['ean'] ?? '')),
            'statut' => LibraryStatut::normalize((string) ($data['statut'] ?? LibraryStatut::COLLECTION)),
        ];

        return [$oeuvre, $library];
    }

    /**
     * @param array<string, mixed> $oeuvre
     * @param array<string, mixed> $data
     * @return array{0: string, 1: int}
     */
    private function resolveLibrarySagaFromOeuvre(array $oeuvre, array $data): array
    {
        $saga = trim((string) ($data['saga'] ?? ''));
        $sagaOrdre = max(0, (int) ($data['saga_ordre'] ?? 0));
        if ($saga === '' && CatalogSchema::hasOeuvreSagaColumns()) {
            $catalogSaga = trim((string) ($oeuvre['saga'] ?? ''));
            if ($catalogSaga !== '') {
                $saga = $catalogSaga;
                $sagaOrdre = max(0, (int) ($oeuvre['saga_ordre'] ?? 0));
            }
        }
        if ($saga === '') {
            $sagaOrdre = 0;
        }

        return [$saga, $sagaOrdre];
    }

    /**
     * @param list<string> $importedColumns
     * @return list<string>
     */
    private function resolveOeuvreMergeFields(array $importedColumns): array
    {
        $base = CollectionExportSchema::filmMergeOnConflictFields();
        $oeuvreFields = array_flip(CatalogSchema::OEUVRE_FIELDS);
        $fields = [];
        foreach ($base as $field) {
            if (isset($oeuvreFields[$field])) {
                $fields[] = $field;
            }
        }
        if ($importedColumns === []) {
            if (!in_array('tmdb_tv_kind', $fields, true)) {
                $fields[] = 'tmdb_tv_kind';
            }

            return $fields;
        }

        $importSet = array_flip($importedColumns);
        $filtered = [];
        foreach ($fields as $field) {
            if (isset($importSet[$field])) {
                $filtered[] = $field;
            }
        }
        if (isset($importSet['tmdb_media_type'])) {
            $filtered[] = 'tmdb_tv_kind';
        }

        return array_values(array_unique($filtered));
    }

    /**
     * @param list<string> $importedColumns
     * @return list<string>
     */
    private function resolveLibraryMergeFields(array $importedColumns): array
    {
        if ($importedColumns === []) {
            return self::LIBRARY_EXPORT_FIELDS;
        }

        $importSet = array_flip($importedColumns);
        $fields = [];
        foreach (self::LIBRARY_EXPORT_FIELDS as $field) {
            if (isset($importSet[$field])) {
                $fields[] = $field;
            }
        }

        return $fields;
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
     * @param array<string, mixed> $oeuvre
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $oeuvrePayload
     * @param array<string, mixed> $importRow
     */
    private function insertOeuvreFromImport(array $oeuvrePayload, array $importRow): int
    {
        $complete = $this->completeOeuvrePayload($oeuvrePayload);
        $requestedId = max(0, (int) ($importRow['oeuvre_id'] ?? 0));

        if ($requestedId <= 0) {
            return $this->oeuvres->insert($complete);
        }

        $duplicate = $this->oeuvres->findByTitreAndRealisateur(
            (string) ($complete['titre'] ?? ''),
            (string) ($complete['realisateur'] ?? '')
        );
        if ($duplicate !== null && (int) ($duplicate['id'] ?? 0) !== $requestedId) {
            $wrongId = (int) $duplicate['id'];
            if ($this->oeuvres->countBibliothequeLinks($wrongId) === 0) {
                $this->oeuvres->deleteById($wrongId);
            } else {
                throw new \RuntimeException(
                    'ID catalogue ' . $requestedId . ' demandé pour « ' . ($complete['titre'] ?? '') . ' », '
                    . 'mais l’ID ' . $wrongId . ' existe déjà (bibliothèque liée).'
                );
            }
        }

        if ($this->oeuvres->findById($requestedId) === null) {
            $this->oeuvres->insertWithId($requestedId, $complete);

            return $requestedId;
        }

        return $this->oeuvres->insert($complete);
    }

    private function completeOeuvrePayload(array $oeuvre): array
    {
        return CatalogSchema::completeOeuvrePayload($oeuvre);
    }

    private function resolvePosterForOeuvre(int $oeuvreId, string $posterUrl): string
    {
        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return '';
        }

        if ($oeuvreId > 0) {
            $local = (new PosterStorage())->ensureLocalForOeuvre($oeuvreId, $posterUrl);
            if ($local !== '') {
                return $local;
            }
        }

        return SecureUrl::sanitizePosterUrl($posterUrl);
    }

    private function cacheOeuvrePosterIfRemote(int $oeuvreId, string $posterUrl): void
    {
        if ($oeuvreId <= 0 || !PosterStorage::isRemoteUrl(trim($posterUrl))) {
            return;
        }

        $local = (new PosterStorage())->cacheRemoteForOeuvre($oeuvreId, trim($posterUrl));
        if ($local !== '') {
            $this->oeuvres->update($oeuvreId, ['poster_url' => $local], ['poster_url']);
        }
    }
}

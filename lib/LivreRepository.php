<?php
/**
 * Catalogue et bibliothèque livres (phase M3).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class LivreRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'oeuvre_livre' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return list<string> */
    public function listKnownCategoryLabels(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $stmt = $this->db->query(
            "SELECT categories FROM oeuvre_livre WHERE TRIM(categories) != ''"
        );
        if ($stmt === false) {
            return [];
        }

        $merged = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            foreach (LivreCategory::parseList((string) ($row['categories'] ?? '')) as $label) {
                $merged[mb_strtolower($label)] = $label;
            }
        }

        return array_values($merged);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInLibrary(
        int $userId,
        int $foyerId,
        string $statut = LibraryStatut::COLLECTION,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $query = ''
    ): array {
        if (!self::isAvailable() || $userId <= 0) {
            return [];
        }

        $statut = LibraryStatut::normalize($statut);
        $params = [
            'domain' => MediaDomain::LIVRE,
            'statut' => $statut,
        ];

        if ($statut === LibraryStatut::WISHLIST) {
            $scopeSql = 'b.user_id = :user_id AND b.statut = :statut';
            $params['user_id'] = $userId;
        } else {
            $scopeSql = 'b.foyer_id = :foyer_id AND b.statut = :statut';
            $params['foyer_id'] = $foyerId;
        }

        $where = [
            $scopeSql,
            'o.media_domain = :domain',
        ];

        $query = trim($query);
        if ($query !== '') {
            $pattern = LikePattern::containsFragment($query);
            $where[] = '(LOWER(o.titre) LIKE LOWER(:q) ESCAPE \'\\\''
                . ' OR LOWER(COALESCE(ol.auteur, \'\')) LIKE LOWER(:q2) ESCAPE \'\\\''
                . ' OR LOWER(COALESCE(ol.editeur, \'\')) LIKE LOWER(:q3) ESCAPE \'\\\''
                . ' OR LOWER(COALESCE(ol.isbn, \'\')) LIKE LOWER(:q4) ESCAPE \'\\\')';
            $params['q'] = $pattern;
            $params['q2'] = $pattern;
            $params['q3'] = $pattern;
            $params['q4'] = $pattern;
        }

        $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $order = match ($sortBy) {
            'annee' => 'o.annee ' . $dir . ', o.titre COLLATE NOCASE ASC',
            'auteur' => 'ol.auteur COLLATE NOCASE ' . $dir . ', o.titre COLLATE NOCASE ASC',
            'editeur' => 'ol.editeur COLLATE NOCASE ' . $dir . ', o.titre COLLATE NOCASE ASC',
            'created' => 'b.created_at ' . $dir . ', o.titre COLLATE NOCASE ASC',
            default => 'o.titre COLLATE NOCASE ' . $dir,
        };

        $sql = 'SELECT b.id AS bib_id, b.statut, b.support_physique, b.created_at AS bib_created_at,
                       o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.synopsis, o.poster_url,
                       o.saga, o.saga_ordre,
                       ol.auteur, ol.isbn, ol.pages, ol.editeur, ol.categories, ol.langue, ol.collection_label,
                       ol.sous_titre, ol.back_cover_url
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $order;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ListOf::assocRows(array_map([$this, 'hydrateRow'], $rows));
    }

    public function countInLibrary(int $userId, int $foyerId, string $statut = LibraryStatut::COLLECTION): int
    {
        return count($this->listInLibrary($userId, $foyerId, $statut));
    }

    /** @return array<string, mixed>|null */
    public function findByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        if (!self::isAvailable() || $bibId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT b.id AS bib_id, b.statut, b.support_physique, b.created_at AS bib_created_at,
                    o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.synopsis, o.poster_url,
                    o.saga, o.saga_ordre,
                    ol.auteur, ol.isbn, ol.pages, ol.editeur, ol.categories, ol.langue, ol.collection_label,
                    ol.sous_titre, ol.back_cover_url
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = ?
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE b.id = ?
               AND (
                    (b.statut = ? AND b.foyer_id = ?)
                    OR (b.statut = ? AND b.user_id = ?)
               )
             LIMIT 1'
        );
        $stmt->execute([
            MediaDomain::LIVRE,
            $bibId,
            LibraryStatut::COLLECTION,
            $foyerId,
            LibraryStatut::WISHLIST,
            $userId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrateRow($row);
    }

    /** @return array<string, mixed>|null */
    public function findCatalogByOeuvreId(int $oeuvreId): ?array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.synopsis, o.poster_url,
                    o.saga, o.saga_ordre,
                    ol.auteur, ol.isbn, ol.pages, ol.editeur, ol.categories, ol.langue, ol.collection_label,
                    ol.sous_titre, ol.back_cover_url
             FROM oeuvres o
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE o.id = ? AND o.media_domain = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId, MediaDomain::LIVRE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrateRow($row);
    }

    /** Identifiant bibliothèque si le livre catalogue est déjà chez l’utilisateur / foyer. */
    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        if (!self::isAvailable() || $oeuvreId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT b.id
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = ?
             WHERE b.oeuvre_id = ?
               AND (
                    (b.statut = ? AND b.foyer_id = ?)
                    OR (b.statut = ? AND b.user_id = ?)
               )
             ORDER BY CASE WHEN b.statut = ? THEN 0 ELSE 1 END, b.id ASC
             LIMIT 1'
        );
        $stmt->execute([
            MediaDomain::LIVRE,
            $oeuvreId,
            LibraryStatut::COLLECTION,
            $foyerId,
            LibraryStatut::WISHLIST,
            $userId,
            LibraryStatut::COLLECTION,
        ]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createWithLibrary(array $data, string $statut, int $userId, int $foyerId): int|string
    {
        if (!self::isAvailable()) {
            return 'Module livres non disponible.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $auteur = trim((string) ($data['auteur'] ?? ''));
        $categories = LivreCategory::normalizeFromPost($data['categories'] ?? '');
        $statut = LibraryStatut::normalize($statut);
        $saga = trim((string) ($data['saga'] ?? ''));
        $sagaOrdre = $saga === '' ? 0 : max(0, (int) ($data['saga_ordre'] ?? 0));
        $sousTitre = trim((string) ($data['sous_titre'] ?? ''));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'titre_original' => trim((string) ($data['titre_original'] ?? '')),
                'realisateur' => $auteur,
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'saga' => $saga,
                'saga_ordre' => $sagaOrdre,
                'media_domain' => MediaDomain::LIVRE,
            ]);

            $this->db->prepare(
                'INSERT INTO oeuvre_livre (
                    oeuvre_id, auteur, isbn, pages, editeur, categories, langue, collection_label,
                    sous_titre, back_cover_url
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $oeuvreId,
                $auteur,
                trim((string) ($data['isbn'] ?? '')),
                max(0, (int) ($data['pages'] ?? 0)),
                trim((string) ($data['editeur'] ?? '')),
                $categories,
                trim((string) ($data['langue'] ?? 'fr')) ?: 'fr',
                trim((string) ($data['collection_label'] ?? '')),
                $sousTitre,
                trim((string) ($data['back_cover_url'] ?? '')),
            ]);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => trim((string) ($data['support_physique'] ?? 'papier')),
            ]);

            $this->db->commit();

            if (LivreCategory::includesJeuxVideo($categories)) {
                (new LivreGameLink())->replaceLinksForBook(
                    $oeuvreId,
                    $this->gameIdsFromPost($data)
                );
            }

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de l’enregistrement du livre.';
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateByBibId(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        $book = $this->findByBibId($bibId, $userId, $foyerId);
        if ($book === null) {
            return 'Livre introuvable.';
        }

        $oeuvreId = (int) ($book['oeuvre_id'] ?? 0);
        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $auteur = trim((string) ($data['auteur'] ?? ''));
        $categories = LivreCategory::normalizeFromPost($data['categories'] ?? '');
        $saga = trim((string) ($data['saga'] ?? ''));
        $sagaOrdre = $saga === '' ? 0 : max(0, (int) ($data['saga_ordre'] ?? 0));
        $sousTitre = trim((string) ($data['sous_titre'] ?? ''));

        $oeuvreFields = [
            'titre' => $titre,
            'titre_original' => trim((string) ($data['titre_original'] ?? '')),
            'realisateur' => $auteur,
            'annee' => max(0, (int) ($data['annee'] ?? 0)),
            'synopsis' => trim((string) ($data['synopsis'] ?? '')),
            'saga' => $saga,
            'saga_ordre' => $sagaOrdre,
        ];
        (new OeuvreRepository())->update($oeuvreId, $oeuvreFields, array_keys($oeuvreFields));

        $this->db->prepare(
            'UPDATE oeuvre_livre SET auteur = ?, isbn = ?, pages = ?, editeur = ?,
             categories = ?, langue = ?, collection_label = ?, sous_titre = ?
             WHERE oeuvre_id = ?'
        )->execute([
            $auteur,
            trim((string) ($data['isbn'] ?? '')),
            max(0, (int) ($data['pages'] ?? 0)),
            trim((string) ($data['editeur'] ?? '')),
            $categories,
            trim((string) ($data['langue'] ?? 'fr')) ?: 'fr',
            trim((string) ($data['collection_label'] ?? '')),
            $sousTitre,
            $oeuvreId,
        ]);

        if (isset($data['support_physique'])) {
            (new BibliothequeRepository())->update($bibId, [
                'support_physique' => trim((string) $data['support_physique']),
            ]);
        }

        if (LivreCategory::includesJeuxVideo($categories)) {
            (new LivreGameLink())->replaceLinksForBook($oeuvreId, $this->gameIdsFromPost($data));
        } else {
            (new LivreGameLink())->replaceLinksForBook($oeuvreId, []);
        }

        return true;
    }

    public function deleteFromLibrary(int $bibId, int $userId, int $foyerId): bool
    {
        $book = $this->findByBibId($bibId, $userId, $foyerId);
        if ($book === null) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM bibliotheque WHERE id = ?');
        $stmt->execute([$bibId]);

        return $stmt->rowCount() > 0;
    }

    /** Passe un livre des envies vers la collection du foyer. */
    public function promoteToCollection(int $bibId, int $userId, int $foyerId): bool
    {
        $book = $this->findByBibId($bibId, $userId, $foyerId);
        if ($book === null || ($book['statut'] ?? '') !== LibraryStatut::WISHLIST) {
            return false;
        }

        return (new BibliothequeRepository())->promoteToCollection($bibId, $userId, $foyerId);
    }

    /** Enregistre une couverture (fichier uploadé ou URL). */
    public function savePoster(int $oeuvreId, string $posterUrlInput, ?string $uploadedBinary = null): void
    {
        if ($oeuvreId <= 0 || !self::isAvailable()) {
            return;
        }

        $storage = new PosterStorage();

        if ($uploadedBinary !== null && $uploadedBinary !== '') {
            $local = $storage->importBinaryForOeuvre($oeuvreId, $uploadedBinary);
            if ($local !== '') {
                (new OeuvreRepository())->update($oeuvreId, ['poster_url' => $local], ['poster_url']);
            }

            return;
        }

        $posterUrlInput = trim($posterUrlInput);
        if ($posterUrlInput === '') {
            return;
        }

        $local = $storage->ensureLocalForOeuvre($oeuvreId, $posterUrlInput);
        if ($local !== '') {
            (new OeuvreRepository())->update($oeuvreId, ['poster_url' => $local], ['poster_url']);

            return;
        }

        $sanitized = SecureUrl::sanitizePosterUrl($posterUrlInput);
        if ($sanitized !== '') {
            (new OeuvreRepository())->update($oeuvreId, ['poster_url' => $sanitized], ['poster_url']);
        }
    }

    /** Enregistre la 4e de couverture (fichier ou URL). */
    public function saveBackCover(int $oeuvreId, string $urlInput, ?string $uploadedBinary = null): void
    {
        if ($oeuvreId <= 0 || !self::isAvailable()) {
            return;
        }

        $storage = new PosterStorage();
        $local = '';

        if ($uploadedBinary !== null && $uploadedBinary !== '') {
            $local = $storage->importBinaryForOeuvreVariant($oeuvreId, $uploadedBinary, 'back');
        } else {
            $urlInput = trim($urlInput);
            if ($urlInput === '') {
                return;
            }
            $local = $storage->ensureLocalForOeuvreVariant($oeuvreId, $urlInput, 'back');
            if ($local === '') {
                $sanitized = SecureUrl::sanitizePosterUrl($urlInput);
                $local = $sanitized;
            }
        }

        if ($local === '') {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_livre SET back_cover_url = ? WHERE oeuvre_id = ?'
        )->execute([$local, $oeuvreId]);
    }

    /** @return list<string> */
    public function listKnownSagas(int $limit = 120): array
    {
        if (!self::isAvailable() || !CatalogSchema::hasOeuvreSagaColumns()) {
            return [];
        }

        $limit = max(1, min($limit, 300));
        $stmt = $this->db->prepare(
            'SELECT DISTINCT o.saga FROM oeuvres o
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE o.media_domain = ? AND TRIM(o.saga) != \'\'
             ORDER BY o.saga COLLATE NOCASE ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, MediaDomain::LIVRE);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $known = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
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

        return $names;
    }

    /**
     * @return list<array{saga: string, book_count: int}>
     */
    public function listSagasWithCounts(int $userId, int $foyerId): array
    {
        if (!self::isAvailable() || !CatalogSchema::hasOeuvreSagaColumns() || $foyerId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT o.saga, COUNT(*) AS book_count
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = ?
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(o.saga) != \'\'
             GROUP BY o.saga
             ORDER BY o.saga COLLATE NOCASE ASC'
        );
        $stmt->execute([MediaDomain::LIVRE, $foyerId, LibraryStatut::COLLECTION]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $name = trim((string) ($row['saga'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'saga' => $name,
                'book_count' => (int) ($row['book_count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Livres d’une saga dans la collection du foyer (tri numéro puis titre).
     *
     * @return list<array<string, mixed>>
     */
    public function findBySaga(string $saga, int $userId, int $foyerId): array
    {
        $saga = trim($saga);
        if ($saga === '' || !self::isAvailable() || $foyerId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT b.id AS bib_id, b.statut, b.support_physique, b.created_at AS bib_created_at,
                    o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.synopsis, o.poster_url,
                    o.saga, o.saga_ordre,
                    ol.auteur, ol.isbn, ol.pages, ol.editeur, ol.categories, ol.langue, ol.collection_label,
                    ol.sous_titre, ol.back_cover_url
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = ?
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE b.foyer_id = ? AND b.statut = ? AND o.saga = ?
             ORDER BY
                CASE WHEN o.saga_ordre > 0 THEN o.saga_ordre ELSE 999999 END ASC,
                o.titre COLLATE NOCASE ASC'
        );
        $stmt->execute([MediaDomain::LIVRE, $foyerId, LibraryStatut::COLLECTION, $saga]);

        return ListOf::assocRows(array_map([$this, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []));
    }

    /**
     * Livres d’une saga dans le foyer (collection + envies), pour le bandeau de fiche.
     * Un seul exemplaire par œuvre : la collection prime sur les envies.
     *
     * @return list<array<string, mixed>>
     */
    public function listFoyerBooksForSaga(string $saga, int $userId, int $foyerId): array
    {
        $saga = trim($saga);
        if ($saga === '' || !self::isAvailable() || $foyerId <= 0 || $userId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT b.id AS bib_id, b.statut, b.support_physique, b.created_at AS bib_created_at,
                    o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.synopsis, o.poster_url,
                    o.saga, o.saga_ordre,
                    ol.auteur, ol.isbn, ol.pages, ol.editeur, ol.categories, ol.langue, ol.collection_label,
                    ol.sous_titre, ol.back_cover_url
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = ?
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE o.saga = ?
               AND (
                    (b.statut = ? AND b.foyer_id = ?)
                    OR (b.statut = ? AND b.user_id = ?)
               )
             ORDER BY
                CASE WHEN o.saga_ordre > 0 THEN o.saga_ordre ELSE 999999 END ASC,
                o.titre COLLATE NOCASE ASC,
                CASE WHEN b.statut = ? THEN 0 ELSE 1 END ASC,
                b.id ASC'
        );
        $stmt->execute([
            MediaDomain::LIVRE,
            $saga,
            LibraryStatut::COLLECTION,
            $foyerId,
            LibraryStatut::WISHLIST,
            $userId,
            LibraryStatut::COLLECTION,
        ]);

        $byOeuvre = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $raw) {
            $row = $this->hydrateRow($raw);
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0 || isset($byOeuvre[$oeuvreId])) {
                continue;
            }
            $byOeuvre[$oeuvreId] = $row;
        }

        return ListOf::assocRows(array_values($byOeuvre));
    }

    /**
     * @return array{ok: bool, updated: int, error: string}
     */
    public function renameSaga(string $oldName, string $newName): array
    {
        $oldName = trim($oldName);
        $newName = trim($newName);
        if ($oldName === '' || $newName === '') {
            return ['ok' => false, 'updated' => 0, 'error' => 'Le nom de la saga est obligatoire.'];
        }
        if ($oldName === $newName) {
            return ['ok' => true, 'updated' => 0, 'error' => ''];
        }
        if (!self::isAvailable() || !CatalogSchema::hasOeuvreSagaColumns()) {
            return ['ok' => false, 'updated' => 0, 'error' => 'Module sagas indisponible.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE oeuvres SET saga = ?, updated_at = datetime(\'now\')
             WHERE media_domain = ? AND saga = ?'
        );
        $stmt->execute([$newName, MediaDomain::LIVRE, $oldName]);

        return ['ok' => true, 'updated' => $stmt->rowCount(), 'error' => ''];
    }

    public static function formatSagaOrdre(int $ordre): string
    {
        return $ordre > 0 ? (string) $ordre : '—';
    }

    /**
     * @param array<string, mixed> $data
     * @return list<int>
     */
    private function gameIdsFromPost(array $data): array
    {
        $raw = $data['game_oeuvre_ids'] ?? $data['game_oeuvre_id'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row): array
    {
        $row['bib_id'] = (int) ($row['bib_id'] ?? 0);
        $row['oeuvre_id'] = (int) ($row['oeuvre_id'] ?? 0);
        $row['annee'] = (int) ($row['annee'] ?? 0);
        $row['pages'] = (int) ($row['pages'] ?? 0);
        $row['saga'] = trim((string) ($row['saga'] ?? ''));
        $row['saga_ordre'] = (int) ($row['saga_ordre'] ?? 0);
        $row['sous_titre'] = trim((string) ($row['sous_titre'] ?? ''));
        $row['back_cover_url'] = trim((string) ($row['back_cover_url'] ?? ''));
        $row['categories'] = (string) ($row['categories'] ?? '');
        $row['category_list'] = LivreCategory::parseList($row['categories']);
        $row['is_jeux_video'] = LivreCategory::includesJeuxVideo($row['categories']);
        $row['display_titre'] = (string) ($row['titre'] ?? '');

        return $row;
    }
}

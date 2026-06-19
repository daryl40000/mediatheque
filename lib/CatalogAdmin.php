<?php
/**
 * Gestion du catalogue partagé (œuvres) — réservé à l’administrateur à terme.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogAdmin
{
    private const PER_PAGE = 40;

    private OeuvreRepository $oeuvres;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->oeuvres = new OeuvreRepository();
    }

    /** Accès à la page catalogue (mono-utilisateur : utilisateur principal). */
    public static function canAccess(): bool
    {
        if (!CatalogSchema::usesCatalogTables(Database::getInstance())) {
            return false;
        }

        return UserContext::canManageCatalog();
    }

    public static function denyUnlessAccess(): void
    {
        if (!self::canAccess()) {
            header('Location: /');
            exit;
        }
    }

    public static function perPage(): int
    {
        return self::PER_PAGE;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOeuvres(
        string $search,
        string $sortBy,
        string $sortDir,
        int $page
    ): array {
        [$sqlSort, $direction] = $this->resolveSort($sortBy, $sortDir);
        $page = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;

        [$whereSql, $params] = $this->searchWhere($search);
        $sql = 'SELECT o.*, (
                    SELECT COUNT(*) FROM bibliotheque b WHERE b.oeuvre_id = o.id
                ) AS library_count
                FROM oeuvres o'
            . $whereSql
            . ' ORDER BY ' . $sqlSort . ' ' . $direction
            . ' LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function countOeuvres(string $search): int
    {
        [$whereSql, $params] = $this->searchWhere($search);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM oeuvres o' . $whereSql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * IDs de toutes les œuvres correspondant au tri / recherche (navigation Préc./Suiv.).
     *
     * @return list<int>
     */
    public function listOeuvreIds(string $search, string $sortBy, string $sortDir): array
    {
        [$sqlSort, $direction] = $this->resolveSort($sortBy, $sortDir);
        [$whereSql, $params] = $this->searchWhere($search);
        $sql = 'SELECT o.id FROM oeuvres o' . $whereSql
            . ' ORDER BY ' . $sqlSort . ' ' . $direction;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $ids = [];
        while ($row = $stmt->fetch()) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Œuvre précédente / suivante dans la liste catalogue (même tri et recherche).
     *
     * @return array{
     *   prev_id: int|null,
     *   next_id: int|null,
     *   position: int,
     *   total: int,
     *   in_list: bool
     * }
     */
    public function getOeuvreNavigation(
        int $oeuvreId,
        string $search,
        string $sortBy,
        string $sortDir
    ): array {
        if ($oeuvreId <= 0) {
            return [
                'prev_id' => null,
                'next_id' => null,
                'position' => 0,
                'total' => 0,
                'in_list' => false,
            ];
        }

        $ids = $this->listOeuvreIds($search, $sortBy, $sortDir);
        $total = count($ids);
        $index = array_search($oeuvreId, $ids, true);
        if ($index === false) {
            return [
                'prev_id' => null,
                'next_id' => null,
                'position' => 0,
                'total' => $total,
                'in_list' => false,
            ];
        }

        return [
            'prev_id' => $index > 0 ? $ids[$index - 1] : null,
            'next_id' => $index < $total - 1 ? $ids[$index + 1] : null,
            'position' => $index + 1,
            'total' => $total,
            'in_list' => true,
        ];
    }

    /**
     * Fiche catalogue + lien bibliothèque de l’utilisateur courant (s’il existe).
     *
     * @return array{oeuvre: array<string, mixed>, library: ?array<string, mixed>, library_count: int}|null
     */
    public function findOeuvreDetail(int $oeuvreId): ?array
    {
        if ($oeuvreId <= 0) {
            return null;
        }

        $oeuvre = $this->oeuvres->findByIdForAdmin($oeuvreId);
        if ($oeuvre === null) {
            return null;
        }

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            UserContext::currentUserId(),
            UserContext::currentFoyerId()
        );
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM bibliotheque WHERE oeuvre_id = ?');
        $stmt->execute([$oeuvreId]);

        return [
            'oeuvre' => $oeuvre,
            'library' => $library,
            'library_count' => (int) $stmt->fetchColumn(),
        ];
    }

    /**
     * Crée une œuvre dans le catalogue (sans entrée bibliothèque).
     *
     * @param array<string, mixed> $data Champs issus de FilmManualEdit::parseFromPost
     * @return int|string ID œuvre ou message d’erreur
     */
    public function createOeuvre(array $data): int|string
    {
        if (max(0, (int) ($data['oeuvre_id'] ?? 0)) > 0) {
            return 'Cette œuvre est déjà au catalogue. Utilisez la liste ci-dessous pour la consulter.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $realisateur = trim((string) ($data['realisateur'] ?? ''));
        if ($this->oeuvres->findByTitreAndRealisateur($titre, $realisateur) !== null) {
            return 'Une œuvre avec ce titre et ce réalisateur existe déjà au catalogue.';
        }

        $types = FilmManualEdit::resolveTmdbTypesForSave($data, []);
        $payload = [
            'titre' => $titre,
            'titre_original' => trim((string) ($data['titre_original'] ?? '')),
            'realisateur' => $realisateur,
            'duree_min' => max(0, (int) ($data['duree_min'] ?? 0)),
            'styles' => trim((string) ($data['styles'] ?? '')),
            'annee' => max(0, (int) ($data['annee'] ?? 0)),
            'nationalite' => TmdbCountries::formatNationaliteList((string) ($data['nationalite'] ?? '')),
            'tmdb_id' => max(0, (int) ($data['tmdb_id'] ?? 0)),
            'tmdb_media_type' => $types['media_type'],
            'tmdb_tv_kind' => $types['tv_kind'],
            'realisateur_tmdb_id' => 0,
            'acteur_1' => trim((string) ($data['acteur_1'] ?? '')),
            'acteur_1_tmdb_id' => 0,
            'acteur_2' => trim((string) ($data['acteur_2'] ?? '')),
            'acteur_2_tmdb_id' => 0,
            'acteur_3' => trim((string) ($data['acteur_3'] ?? '')),
            'acteur_3_tmdb_id' => 0,
            'poster_url' => SecureUrl::sanitizePosterUrl((string) ($data['poster_url'] ?? '')),
            'synopsis' => trim((string) ($data['synopsis'] ?? '')),
            'moncine_kind' => MoncineContentKind::normalize((string) ($data['moncine_kind'] ?? '')),
            'omdb_imdb_id' => '',
            'omdb_enriched_at' => null,
        ];

        $oeuvreId = $this->oeuvres->insert($payload);
        $this->cachePosterIfRemote($oeuvreId, (string) ($payload['poster_url'] ?? ''));

        return $oeuvreId;
    }

    /**
     * Crée un jeu vidéo dans le catalogue partagé (sans bibliothèque).
     *
     * @param array<string, mixed> $data
     * @return int|string ID œuvre ou message d’erreur
     */
    public function createGameOeuvre(array $data): int|string
    {
        if (!GameRepository::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        $oeuvreId = (new GameRepository())->createCatalogOnly($data);
        if (!is_int($oeuvreId)) {
            return $oeuvreId;
        }

        $this->cachePosterIfRemote($oeuvreId, (string) ($data['poster_url'] ?? ''));

        return $oeuvreId;
    }

    /**
     * Supprime une œuvre du catalogue (et les entrées bibliothèque liées, en cascade).
     *
     * @return true|string
     */
    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateOeuvreManual(int $oeuvreId, array $data): bool|string
    {
        return (new FilmRepository())->updateOeuvreManual($oeuvreId, $data);
    }

    /**
     * Enregistre une affiche depuis un fichier image envoyé par l’administrateur.
     *
     * @return true|string true si OK, sinon message d’erreur
     */
    public function uploadPosterFile(int $oeuvreId, string $tmpPath, int $fileSize): bool|string
    {
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        if ($this->oeuvres->findById($oeuvreId) === null) {
            return 'Œuvre introuvable.';
        }

        $maxBytes = defined('MONCINE_POSTER_MAX_BYTES') ? (int) MONCINE_POSTER_MAX_BYTES : 2_097_152;
        if ($fileSize <= 0 || $fileSize > $maxBytes) {
            $maxMo = (int) ceil($maxBytes / 1024 / 1024);

            return 'Image trop volumineuse (maximum ' . $maxMo . ' Mo).';
        }

        if ($tmpPath === '' || !is_readable($tmpPath)) {
            return 'Impossible de lire le fichier envoyé.';
        }

        $binary = file_get_contents($tmpPath);
        if ($binary === false || $binary === '') {
            return 'Impossible de lire le fichier envoyé.';
        }

        $local = (new PosterStorage())->importBinaryForOeuvre($oeuvreId, $binary);
        if ($local === '') {
            return 'Format non reconnu. Utilisez une image JPEG, PNG ou WebP.';
        }

        $this->oeuvres->update($oeuvreId, ['poster_url' => $local], ['poster_url']);

        return true;
    }

    /**
     * Import / mise à jour d’une œuvre catalogue depuis un export admin.
     *
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns
     */
    public function importOeuvreFromExport(array $data, array $importedColumns = []): void
    {
        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            throw new \RuntimeException('Le titre est obligatoire pour le catalogue.');
        }

        $realisateur = trim((string) ($data['realisateur'] ?? ''));
        $mediaDomain = MediaDomain::normalize((string) ($data['media_domain'] ?? MediaDomain::FILM));
        $oeuvreId = max(0, (int) ($data['oeuvre_id'] ?? 0));
        $importSet = $importedColumns !== [] ? array_flip($importedColumns) : null;

        $payload = [];
        foreach (CatalogExportSchema::oeuvreDatabaseFields() as $field) {
            if ($importSet !== null && !isset($importSet[$field])) {
                continue;
            }
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        $payload['titre'] = $titre;
        $payload['realisateur'] = $realisateur;
        $payload['media_domain'] = $mediaDomain;

        if ($oeuvreId > 0) {
            $duplicate = $this->oeuvres->findByTitreRealisateurAndDomain(
                $titre,
                $realisateur,
                $mediaDomain
            );
            if ($duplicate !== null && (int) ($duplicate['id'] ?? 0) !== $oeuvreId) {
                $wrongId = (int) $duplicate['id'];
                if ($this->oeuvres->countBibliothequeLinks($wrongId) > 0) {
                    throw new \RuntimeException(
                        '« ' . $titre . ' » est déjà en base avec l’ID '
                        . $wrongId . ' (fichier : ' . $oeuvreId . '). '
                        . 'Cochez « Réinitialiser le catalogue avant import » ou supprimez les bibliothèques liées.'
                    );
                }
                $this->oeuvres->deleteById($wrongId);
                $duplicate = null;
            }

            $existing = $this->oeuvres->findByIdForAdmin($oeuvreId);
            if ($existing === null) {
                $this->oeuvres->insertWithId($oeuvreId, $this->completeOeuvrePayload($payload));
                $this->cachePosterIfRemote($oeuvreId, (string) ($payload['poster_url'] ?? ''));
                CatalogDomainExtensions::importForOeuvre($oeuvreId, $data, $importedColumns);

                return;
            }

            $fields = array_keys($payload);
            $this->oeuvres->update($oeuvreId, $payload, $fields);
            $this->oeuvres->updateMediaDomain($oeuvreId, $mediaDomain);
            $this->cachePosterIfRemote($oeuvreId, (string) ($payload['poster_url'] ?? $existing['poster_url'] ?? ''));
            CatalogDomainExtensions::importForOeuvre($oeuvreId, $data, $importedColumns);

            return;
        }

        $duplicate = $this->oeuvres->findByTitreRealisateurAndDomain($titre, $realisateur, $mediaDomain);
        if ($duplicate !== null) {
            $fields = array_keys($payload);
            $dupId = (int) $duplicate['id'];
            $this->oeuvres->update($dupId, $payload, $fields);
            $this->oeuvres->updateMediaDomain($dupId, $mediaDomain);
            $this->cachePosterIfRemote($dupId, (string) ($payload['poster_url'] ?? ''));
            CatalogDomainExtensions::importForOeuvre($dupId, $data, $importedColumns);

            return;
        }

        $newId = $this->oeuvres->insert($this->completeOeuvrePayload($payload));
        $this->cachePosterIfRemote($newId, (string) ($payload['poster_url'] ?? ''));
        CatalogDomainExtensions::importForOeuvre($newId, $data, $importedColumns);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function completeOeuvrePayload(array $payload): array
    {
        foreach (CatalogSchema::OEUVRE_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                continue;
            }
            $payload[$field] = match ($field) {
                'duree_min', 'annee', 'tmdb_id', 'realisateur_tmdb_id',
                'acteur_1_tmdb_id', 'acteur_2_tmdb_id', 'acteur_3_tmdb_id' => 0,
                'omdb_enriched_at' => null,
                'moncine_kind' => MoncineContentKind::FILM,
                default => '',
            };
        }

        if (!array_key_exists('media_domain', $payload)) {
            $payload['media_domain'] = MediaDomain::FILM;
        }

        return $payload;
    }

    /**
     * Supprime toutes les œuvres du catalogue (et les entrées bibliothèque — CASCADE).
     * À utiliser avant un import migration avec conservation des ID catalogue.
     */
    public function clearCatalogForImport(): void
    {
        $this->oeuvres->deleteAll();
    }

    public function deleteOeuvre(int $oeuvreId): bool|string
    {
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        if ($this->oeuvres->findById($oeuvreId) === null) {
            return 'Œuvre introuvable ou déjà supprimée.';
        }

        if (!$this->oeuvres->deleteById($oeuvreId)) {
            return 'Impossible de supprimer cette œuvre.';
        }

        $adminId = Auth::currentUserId();
        if ($adminId > 0) {
            (new CatalogAuditLog())->log(
                $adminId,
                CatalogAuditLog::ACTION_DELETE,
                $oeuvreId,
                'Suppression du catalogue'
            );
        }

        return true;
    }

    public function sortUrl(string $column, string $currentSort, string $currentDir, string $search, int $page): string
    {
        $newDir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) !== 'desc') {
            $newDir = 'desc';
        }

        return View::catalogueUrl(trim($search), $column, $newDir, max(1, $page));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSort(string $sortBy, string $sortDir): array
    {
        $allowed = [
            'titre' => 'o.titre COLLATE FRENCH_NOCASE',
            'realisateur' => 'o.realisateur COLLATE FRENCH_NOCASE',
            'annee' => 'o.annee',
            'created_at' => 'o.created_at',
        ];
        $sqlSort = $allowed[$sortBy] ?? $allowed['titre'];
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        return [$sqlSort, $direction];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function searchWhere(string $search): array
    {
        $parts = [];
        $params = [];

        $search = trim($search);
        if ($search !== '') {
            $pattern = SearchMatch::foldedContainsPattern($search);
            $parts[] = '(fold_search(o.titre) LIKE :catalog_search ESCAPE \'\\\'
                OR fold_search(COALESCE(o.realisateur, \'\')) LIKE :catalog_search_real ESCAPE \'\\\')';
            $params['catalog_search'] = $pattern;
            $params['catalog_search_real'] = $pattern;
        }

        if ($parts === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $parts), $params];
    }

    private function cachePosterIfRemote(int $oeuvreId, string $posterUrl): void
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

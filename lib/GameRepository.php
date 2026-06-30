<?php
/**
 * Jeux vidéo : catalogue (oeuvres + oeuvre_jeu) et collection utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameRepository
{
    /** Colonnes triables sur les listes jeux. */
    private const SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'platform' => 'oj.platform COLLATE NOCASE',
        'studio' => 'oj.studio COLLATE FRENCH_NOCASE',
        'genre' => 'oj.genre COLLATE FRENCH_NOCASE',
        'note' => 'note_max',
        'finished_at' => 'derniere_completion',
    ];

    /** @return list<string> */
    public static function sortableColumns(): array
    {
        return ['titre', 'annee', 'genre', 'studio', 'support', 'note', 'finished_at'];
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return in_array($sortBy, self::sortableColumns(), true);
    }

    private static function sortOrderExpression(string $sortBy): string
    {
        if ($sortBy === 'support') {
            if (GameSchema::hasEditionColumns()) {
                return 'oj.physical_supports COLLATE NOCASE, oj.digital_stores COLLATE NOCASE, oj.is_digital';
            }

            return 'oj.is_digital';
        }

        if ($sortBy === 'finished_at' && !GameCompletionRepository::isAvailable()) {
            return self::SORT_COLUMNS['titre'];
        }

        return self::SORT_COLUMNS[$sortBy] ?? self::SORT_COLUMNS['titre'];
    }

    private PDO $db;

    private ?GameLinkedGamesQuery $linkedGamesQuery = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function linkedGames(): GameLinkedGamesQuery
    {
        return $this->linkedGamesQuery ??= new GameLinkedGamesQuery($this->db);
    }

    public static function isAvailable(): bool
    {
        return GameSchema::tableExists() && CatalogSchema::usesCatalogTables(Database::getInstance());
    }

    public static function tableExists(): bool
    {
        return GameSchema::tableExists();
    }

    public static function hasEditionColumns(): bool
    {
        return GameSchema::hasEditionColumns();
    }

    public static function hasExtensionColumns(): bool
    {
        return GameSchema::hasExtensionColumns();
    }

    public static function hasIgdbMetadataColumns(): bool
    {
        return GameSchema::hasIgdbMetadataColumns();
    }

    public static function hasRemakeColumns(): bool
    {
        return GameSchema::hasRemakeColumns();
    }

    public static function hasIgdbColumns(): bool
    {
        return GameSchema::hasIgdbColumns();
    }

    /**
     * Valide extension / remake (mutuellement exclusifs, jeu lié obligatoire).
     *
     * @param array<string, mixed> $data
     */
    public static function validateGameRelationFlags(array $data, int $selfOeuvreId = 0): ?string
    {
        return GameRelations::validateFlags($data, $selfOeuvreId);
    }

    public static function hasTestedOnLinuxColumn(): bool
    {
        return GameSchema::hasTestedOnLinuxColumn();
    }

    public static function hasLinuxNotSupportedColumn(): bool
    {
        return GameSchema::hasLinuxNotSupportedColumn();
    }

    public static function hasNonPretableColumn(): bool
    {
        return GameSchema::hasNonPretableColumn();
    }

    /** @param array<string, mixed> $post */
    public static function nonPretableFromPost(array $post): bool
    {
        return !empty($post['non_pretable']);
    }

    public static function hasPlatformsColumn(): bool
    {
        return GameSchema::hasPlatformsColumn();
    }

    public static function hasOwnedPlatformsColumn(): bool
    {
        return GameSchema::hasOwnedPlatformsColumn();
    }

    /**
     * Plateformes catalogue depuis un formulaire (cases platforms[] ou ancien select platform).
     *
     * @param array<string, mixed> $post
     * @return array{platform: string, platforms: string, platform_list: list<string>}
     */
    public static function catalogPlatformsFromPost(array $post): array
    {
        return self::resolveCatalogPlatformFields($post);
    }

    /**
     * Plateformes possédées (sous-ensemble du catalogue).
     *
     * @param array<string, mixed> $post
     * @return array{owned_platforms: string, owned_platform_list: list<string>}
     */
    public static function ownedPlatformsFromPost(array $post, string $catalogPlatformsCsv): array
    {
        $ownedCsv = GamePlatformList::normalizeOwnedFromPost(
            $post['owned_platforms'] ?? [],
            GamePlatformList::parseList($catalogPlatformsCsv)
        );
        if ($ownedCsv === '' && isset($post['platform'])) {
            $legacy = GamePlatform::normalize((string) $post['platform']);
            $catalogKeys = GamePlatformList::parseList($catalogPlatformsCsv);
            if ($legacy !== '' && in_array($legacy, $catalogKeys, true)) {
                $ownedCsv = $legacy;
            }
        }

        return [
            'owned_platforms' => $ownedCsv,
            'owned_platform_list' => GamePlatformList::parseList($ownedCsv),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{platform: string, platforms: string, platform_list: list<string>}
     */
    public static function resolveCatalogPlatformFields(array $data): array
    {
        $keys = [];
        if (isset($data['platform_list']) && is_array($data['platform_list'])) {
            $keys = GamePlatformList::parseList(GamePlatformList::serializeList($data['platform_list']));
        } elseif (isset($data['platforms'])) {
            if (is_array($data['platforms'])) {
                $keys = GamePlatformList::parseList(GamePlatformList::serializeList($data['platforms']));
            } else {
                $keys = GamePlatformList::parseList((string) $data['platforms']);
            }
        } elseif (isset($data['platform'])) {
            $single = GamePlatform::normalize((string) $data['platform']);
            if ($single !== '') {
                $keys = [$single];
            }
        }

        $platformsCsv = GamePlatformList::serializeList($keys);
        $primary = GamePlatformList::primaryKey($keys);

        return [
            'platform' => $primary,
            'platforms' => $platformsCsv,
            'platform_list' => $keys,
        ];
    }

    /**
     * Exemplaires / éditions depuis un formulaire POST.
     *
     * @param array<string, mixed> $post
     * @return array{physical_supports: string, digital_stores: string, is_digital: bool}
     */
    public static function editionPayloadFromPost(array $post): array
    {
        $keys = GamePlatform::selectedKeysFromPost($post, 'owned_platforms');
        if ($keys === []) {
            $keys = GamePlatform::selectedKeysFromPost($post, 'platforms', 'platform');
        }
        $platform = GamePlatformList::primaryKey($keys);
        $physicalSupports = GameSchema::hasEditionColumns()
            ? GamePhysicalSupport::normalizeFromPost($post['physical_supports'] ?? [])
            : '';
        $digitalStores = GameSchema::hasEditionColumns()
            ? GameDigitalStore::buildFromPostForPlatforms($post, $keys)
            : '';
        $isDigital = !empty($post['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);

        return [
            'physical_supports' => $physicalSupports,
            'digital_stores' => $digitalStores,
            'is_digital' => $isDigital,
        ];
    }

    /** Cases Linux (jeux PC uniquement, mutuellement exclusives). */
    public static function linuxFlagsFromPost(array $post): array
    {
        $keys = GamePlatform::selectedKeysFromPost($post, 'owned_platforms');
        if ($keys === []) {
            $keys = GamePlatform::selectedKeysFromPost($post, 'platforms', 'platform');
        }
        if (!in_array(GamePlatform::PC, $keys, true)) {
            return [
                'tested_on_linux' => false,
                'linux_not_supported' => false,
            ];
        }

        $notSupported = !empty($post['linux_not_supported']);
        $tested = !$notSupported && !empty($post['tested_on_linux']);

        return [
            'tested_on_linux' => $tested,
            'linux_not_supported' => $notSupported,
        ];
    }

    /** @deprecated Utiliser linuxFlagsFromPost() */
    public static function testedOnLinuxFromPost(array $post): bool
    {
        return self::linuxFlagsFromPost($post)['tested_on_linux'];
    }

    /**
     * Données catalogue jeu depuis un formulaire POST (sans bibliothèque).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function catalogPayloadFromPost(array $post): array
    {
        return [
            'oeuvre_id' => max(0, (int) ($post['oeuvre_id'] ?? 0)),
            'titre' => trim((string) ($post['titre'] ?? '')),
            'titre_original' => trim((string) ($post['titre_original'] ?? '')),
            'annee' => max(0, (int) ($post['annee'] ?? 0)),
            'studio' => trim((string) ($post['studio'] ?? '')),
            'editeur' => trim((string) ($post['editeur'] ?? '')),
            'genre' => GameGenre::normalizeFromPost($post['genres'] ?? []),
            'franchise' => trim((string) ($post['franchise'] ?? '')),
            'game_mode' => GameGenre::normalizeFromPost($post['game_modes'] ?? []),
            'theme' => GameGenre::normalizeFromPost($post['themes'] ?? []),
            'alternative_names' => GameGenre::normalizeFromPost($post['alternative_names'] ?? []),
            'platform' => GamePlatform::normalize((string) ($post['platform'] ?? '')),
            'synopsis' => trim((string) ($post['synopsis'] ?? '')),
            'poster_url' => SecureUrl::sanitizePosterUrl((string) ($post['poster_url'] ?? '')),
            'is_extension' => !empty($post['is_extension']),
            'base_game_oeuvre_id' => max(0, (int) ($post['base_game_oeuvre_id'] ?? 0)),
            'is_remake' => !empty($post['is_remake']),
            'original_game_oeuvre_id' => max(0, (int) ($post['original_game_oeuvre_id'] ?? 0)),
        ];
    }

    /**
     * Crée une fiche jeu dans le catalogue partagé (sans entrée bibliothèque).
     *
     * @param array<string, mixed> $data
     * @return int|string ID œuvre ou message d’erreur
     */
    public function createCatalogOnly(array $data): int|string
    {
        if (!self::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        if (max(0, (int) ($data['oeuvre_id'] ?? 0)) > 0) {
            return 'Ce jeu est déjà au catalogue. Utilisez la liste ci-dessous pour le consulter.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $existing = (new OeuvreRepository())->findByTitreRealisateurAndDomain(
            $titre,
            '',
            MediaDomain::JEU
        );
        if ($existing !== null) {
            return 'Un jeu avec ce titre existe déjà au catalogue.';
        }

        $platformFields = self::resolveCatalogPlatformFields($data);
        $platform = $platformFields['platform'];
        $relationError = self::validateGameRelationFlags($data);
        if ($relationError !== null) {
            return $relationError;
        }
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'titre_original' => trim((string) ($data['titre_original'] ?? '')),
                'realisateur' => '',
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::JEU,
            ]);

            $this->insertCatalogGameRow(
                $oeuvreId,
                $data,
                $platform,
                $platformFields['platforms'],
                false,
                $isExtension,
                $baseGameOeuvreId
            );

            $this->db->commit();

            return $oeuvreId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de l’enregistrement du jeu au catalogue.';
        }
    }

    /**
     * Jeux présents dans la bibliothèque (collection ou envies).
     *
     * @return list<array<string, mixed>>
     */
    public function listInLibrary(
        int $userId,
        int $foyerId,
        string $statut = LibraryStatut::COLLECTION,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        ?GameListFilter $filter = null
    ): array {
        if (!self::isAvailable()) {
            return [];
        }

        if (!self::isValidSortColumn($sortBy)) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = self::sortOrderExpression($sortBy);
        if ($sortBy === 'finished_at' && GameCompletionRepository::isAvailable()) {
            $orderExpr = 'derniere_completion IS NULL ASC, derniere_completion ' . $direction;
        }

        $params = [];
        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::normalize($statut));

        $where = [
            'o.media_domain = :game_domain',
            $userWhere,
        ];
        $params['game_domain'] = MediaDomain::JEU;
        $params['history_user_id'] = $userId;
        $params['foyer_id_rating'] = $foyerId;

        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            [$searchSql, $searchParams] = self::gameSearchSqlConditions(
                $searchQuery,
                includeGenre: true,
                includePrefix: false,
                titleParam: 'q',
            );
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        ($filter ?? GameListFilter::empty())->applyToSql($where, $params);

        $sql = 'SELECT ' . self::selectGameRow() . self::selectGameHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $orderExpr . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([GameRowMapper::class, 'hydrateGameRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Passe une envie jeu dans la collection du foyer. */
    public function promoteToCollection(int $bibId, int $userId, int $foyerId): bool
    {
        $game = $this->findByBibId($bibId, $userId, $foyerId);
        if ($game === null || ($game['statut'] ?? '') !== LibraryStatut::WISHLIST) {
            return false;
        }

        return (new BibliothequeRepository())->promoteToCollection($bibId, $userId, $foyerId);
    }

    /** Retire un jeu de la bibliothèque (collection ou envies) et efface ses notes/sessions. */
    public function deleteById(int $bibId, int $userId, int $foyerId): bool
    {
        if ($this->findByBibId($bibId, $userId, $foyerId) === null) {
            return false;
        }

        $this->db->prepare('DELETE FROM historique WHERE film_id = ?')->execute([$bibId]);

        return (new BibliothequeRepository())->deleteById($bibId, $userId, $foyerId);
    }

    /** @return true|string */
    public function updateTestedOnLinux(int $bibId, int $userId, int $foyerId, bool $testedOnLinux): bool|string
    {
        if (!self::hasTestedOnLinuxColumn()) {
            return 'Option Linux non disponible (migration en cours).';
        }

        $game = $this->findByBibId($bibId, $userId, $foyerId);
        if ($game === null) {
            return 'Jeu introuvable.';
        }

        if (GamePlatform::normalize((string) ($game['platform'] ?? '')) !== GamePlatform::PC && $testedOnLinux) {
            return 'Seuls les jeux PC peuvent être marqués comme testés sous Linux.';
        }

        $this->saveLinuxFlags(
            $bibId,
            GamePlatform::normalize((string) ($game['platform'] ?? '')),
            $testedOnLinux,
            false
        );

        return true;
    }

    private function saveLinuxFlags(int $bibId, string $platform, bool $testedOnLinux, bool $linuxNotSupported): void
    {
        if (!self::hasTestedOnLinuxColumn() || $bibId <= 0) {
            return;
        }

        if ($platform !== GamePlatform::PC) {
            $testedOnLinux = false;
            $linuxNotSupported = false;
        } elseif ($testedOnLinux && $linuxNotSupported) {
            $linuxNotSupported = false;
        }

        $sql = 'UPDATE bibliotheque SET tested_on_linux = ?';
        $params = [$testedOnLinux ? 1 : 0];

        if (self::hasLinuxNotSupportedColumn()) {
            $sql .= ', linux_not_supported = ?';
            $params[] = $linuxNotSupported ? 1 : 0;
        }

        $sql .= ' WHERE id = ?';
        $params[] = $bibId;

        $this->db->prepare($sql)->execute($params);
    }

    private function saveNonPretable(int $bibId, bool $nonPretable): void
    {
        if (!self::hasNonPretableColumn() || $bibId <= 0) {
            return;
        }

        $this->db->prepare('UPDATE bibliotheque SET non_pretable = ? WHERE id = ?')
            ->execute([$nonPretable ? 1 : 0, $bibId]);
    }

    private function saveOwnedPlatforms(int $bibId, string $ownedPlatformsCsv): void
    {
        if (!self::hasOwnedPlatformsColumn() || $bibId <= 0) {
            return;
        }

        $this->db->prepare('UPDATE bibliotheque SET owned_platforms = ? WHERE id = ?')
            ->execute([$ownedPlatformsCsv, $bibId]);
    }

    /**
     * Recherche dans le catalogue jeux (autocomplétion, pont magazine).
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalog(string $query, int $limit = 20): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $prefetchLimit = min(max($limit * 8, 80), 250);
        $params = ['game_domain' => MediaDomain::JEU];
        $where = ['o.media_domain = :game_domain'];

        $query = trim($query);
        if ($query !== '') {
            [$searchSql, $searchParams] = self::gameSearchSqlConditions(
                $query,
                includeGenre: false,
                includePrefix: true,
                titleParam: 'q_titre',
            );
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        $sql = 'SELECT ' . self::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE ASC'
            . ' LIMIT ' . ($query !== '' ? $prefetchLimit : $limit);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($query !== '') {
            $rows = SearchMatch::filterRankLimit(
                $rows,
                $query,
                static fn (array $row): string => GameTitle::searchText($row)
                    . ' '
                    . (string) ($row['studio'] ?? ''),
                $limit
            );
        }

        return array_map([GameRowMapper::class, 'hydrateCatalogRow'], $rows);
    }

    /** @return array<string, mixed>|null */
    public function findByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        if (!self::isAvailable() || $bibId <= 0) {
            return null;
        }

        $params = [
            'bib_id' => $bibId,
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ];

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectGameRow()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE b.id = :bib_id'
            . ' AND o.media_domain = :game_domain'
            . ' AND ('
            . '   (b.statut = :collection AND b.foyer_id = :foyer_id)'
            . '   OR (b.statut = :wishlist AND b.user_id = :user_id)'
            . ' )'
            . ' LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $game = GameRowMapper::hydrateGameRow($row);
        if (GameSchema::hasExtensionColumns()) {
            $baseId = (int) ($game['base_game_oeuvre_id'] ?? 0);
            if (!empty($game['is_extension']) && $baseId > 0) {
                $base = $this->findCatalogByOeuvreId($baseId);
                if ($base !== null) {
                    $game['base_game_label'] = (string) ($base['display_label'] ?? $base['titre'] ?? '');
                    $game['base_game_titre'] = (string) ($base['titre'] ?? '');
                }
            }
        }
        if (self::hasRemakeColumns()) {
            $originalId = (int) ($game['original_game_oeuvre_id'] ?? 0);
            if (!empty($game['is_remake']) && $originalId > 0) {
                $original = $this->findCatalogByOeuvreId($originalId);
                if ($original !== null) {
                    $game['original_game_label'] = (string) ($original['display_label'] ?? $original['titre'] ?? '');
                    $game['original_game_titre'] = (string) ($original['titre'] ?? '');
                }
            }
        }

        return $game;
    }

    /**
     * Liste des extensions d’un jeu de base présentes dans la bibliothèque (collection du foyer ou envies).
     *
     * @return list<array{bib_id:int, oeuvre_id:int, titre:string, annee:int, platform_short:string, display_label:string}>
     */
    public function listExtensionsForBaseGame(int $baseOeuvreId, int $userId, int $foyerId): array
    {
        if (!self::isAvailable() || !GameSchema::hasExtensionColumns() || $baseOeuvreId <= 0) {
            return [];
        }

        return $this->linkedGames()->listLibraryExtensions($baseOeuvreId, $userId, $foyerId);
    }

    /**
     * Remakes d’un jeu d’origine présents dans la bibliothèque (collection du foyer ou envies).
     *
     * @return list<array{bib_id:int, oeuvre_id:int, titre:string, annee:int, platform_short:string, display_label:string}>
     */
    public function listRemakesForOriginalGame(int $originalOeuvreId, int $userId, int $foyerId): array
    {
        if (!self::isAvailable() || !GameSchema::hasRemakeColumns() || $originalOeuvreId <= 0) {
            return [];
        }

        return $this->linkedGames()->listLibraryRemakes($originalOeuvreId, $userId, $foyerId);
    }

    /** Fiche catalogue jeu (indépendamment du contexte média actif).
     *
     * @return array<string, mixed>|null
     */
    public function findCatalogByOeuvreId(int $oeuvreId): ?array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.id = ? AND o.media_domain = ?'
            . ' LIMIT 1'
        );
        $stmt->execute([$oeuvreId, MediaDomain::JEU]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? GameRowMapper::hydrateCatalogRow($row) : null;
    }

    /** Id bibliothèque (collection ou envies) pour une fiche catalogue jeu, ou null. */
    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        if ($oeuvreId <= 0) {
            return null;
        }

        $library = (new BibliothequeRepository())->findByOeuvreId($oeuvreId, $userId, $foyerId);
        if ($library === null) {
            return null;
        }

        $game = $this->findCatalogByOeuvreId($oeuvreId);

        return $game !== null ? (int) ($library['id'] ?? 0) : null;
    }

    /**
     * Ajoute une fiche jeu catalogue à la bibliothèque (sans formulaire détaillé).
     *
     * @return int|string bib_id ou message d’erreur
     */
    public function addFromCatalogOeuvre(
        int $oeuvreId,
        string $statut,
        int $userId,
        int $foyerId,
        array $libraryDetails = []
    ): int|string {
        if (!self::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        if ($this->findCatalogByOeuvreId($oeuvreId) === null) {
            return 'Ce jeu n’existe pas dans le catalogue.';
        }

        $statut = LibraryStatut::normalize($statut);
        $bibRepo = new BibliothequeRepository();
        $library = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId);
        if ($library !== null) {
            $bibId = (int) ($library['id'] ?? 0);
            $currentStatut = (string) ($library['statut'] ?? LibraryStatut::COLLECTION);
            if ($currentStatut === $statut) {
                return 'Ce jeu existe déjà dans « ' . LibraryStatut::label($statut) . ' ».';
            }

            $update = ['statut' => $statut];
            if ($statut === LibraryStatut::COLLECTION) {
                $update['foyer_id'] = $foyerId;
            } else {
                $update['foyer_id'] = null;
            }
            $bibRepo->update($bibId, $update);
        } else {
            $bibId = $bibRepo->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => '',
            ]);
        }

        $this->applyLibraryDetailsAfterCatalogAttach($bibId, $oeuvreId, $libraryDetails);

        return $bibId;
    }

    /**
     * Complète une entrée bibliothèque après rattachement au catalogue (Linux, éditions).
     *
     * @param array<string, mixed> $details
     */
    private function applyLibraryDetailsAfterCatalogAttach(int $bibId, int $oeuvreId, array $details): void
    {
        if ($bibId <= 0 || $details === []) {
            return;
        }

        $game = $this->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return;
        }

        $catalogCsv = GamePlatformList::serializeList(GamePlatformList::catalogKeysFromRow($game));
        $ownedFields = self::ownedPlatformsFromPost($details, $catalogCsv);
        if ($ownedFields['owned_platforms'] !== '' || array_key_exists('owned_platforms', $details)) {
            $this->saveOwnedPlatforms($bibId, $ownedFields['owned_platforms']);
        }

        $ownedList = GamePlatformList::parseList($ownedFields['owned_platforms']);
        if ($ownedList === []) {
            $ownedList = GamePlatformList::catalogKeysFromRow($game);
        }
        $linuxPlatform = in_array(GamePlatform::PC, $ownedList, true)
            ? GamePlatform::PC
            : GamePlatform::normalize((string) ($details['platform'] ?? $game['platform'] ?? ''));
        if (array_key_exists('tested_on_linux', $details) || array_key_exists('linux_not_supported', $details)) {
            $this->saveLinuxFlags(
                $bibId,
                $linuxPlatform,
                !empty($details['tested_on_linux']),
                !empty($details['linux_not_supported'])
            );
        }

        if (array_key_exists('non_pretable', $details)) {
            $this->saveNonPretable($bibId, !empty($details['non_pretable']));
        }

        if (!self::hasEditionColumns()) {
            return;
        }

        $physicalSupports = (string) ($details['physical_supports'] ?? '');
        $digitalStores = (string) ($details['digital_stores'] ?? '');
        $isDigital = !empty($details['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);

        if ($physicalSupports === '' && $digitalStores === '' && !$isDigital) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_jeu SET physical_supports = ?, digital_stores = ?, is_digital = ? WHERE oeuvre_id = ?'
        )->execute([
            $physicalSupports,
            $digitalStores,
            $isDigital ? 1 : 0,
            $oeuvreId,
        ]);
    }

    /**
     * Extensions catalogue rattachées à un jeu de base (toutes fiches, pas seulement la bibliothèque).
     *
     * @return list<array<string, mixed>>
     */
    public function listCatalogExtensionsForBaseGame(int $baseOeuvreId): array
    {
        if (!self::isAvailable() || !GameSchema::hasExtensionColumns() || $baseOeuvreId <= 0) {
            return [];
        }

        return $this->linkedGames()->listCatalogExtensions(
            $baseOeuvreId,
            self::selectCatalogRow(),
            [GameRowMapper::class, 'hydrateCatalogRow']
        );
    }

    /**
     * Remakes catalogue rattachés à un jeu d’origine (toutes fiches, pas seulement la bibliothèque).
     *
     * @return list<array<string, mixed>>
     */
    public function listCatalogRemakesForOriginalGame(int $originalOeuvreId): array
    {
        if (!self::isAvailable() || !GameSchema::hasRemakeColumns() || $originalOeuvreId <= 0) {
            return [];
        }

        return $this->linkedGames()->listCatalogRemakes(
            $originalOeuvreId,
            self::selectCatalogRow(),
            [GameRowMapper::class, 'hydrateCatalogRow']
        );
    }

    /**
     * Crée une fiche jeu catalogue + entrée bibliothèque.
     *
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createWithLibrary(
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        if (!self::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $statut = LibraryStatut::normalize($statut);
        $platformFields = self::resolveCatalogPlatformFields($data);
        $platform = $platformFields['platform'];
        $ownedFields = self::ownedPlatformsFromPost($data, $platformFields['platforms']);
        if ($ownedFields['owned_platforms'] === '' && $platformFields['platforms'] !== '') {
            $ownedFields = [
                'owned_platforms' => $platformFields['platforms'],
                'owned_platform_list' => $platformFields['platform_list'],
            ];
        }
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');
        $isDigital = !empty($data['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, !empty($data['is_digital']));
        $relationError = self::validateGameRelationFlags($data);
        if ($relationError !== null) {
            return $relationError;
        }
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::JEU,
            ]);

            $this->insertCatalogGameRow($oeuvreId, $data, $platform, $platformFields['platforms'], $isDigital, $isExtension, $baseGameOeuvreId);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => trim((string) ($data['support_physique'] ?? '')),
            ]);

            $linuxPlatform = in_array(GamePlatform::PC, $ownedFields['owned_platform_list'], true)
                ? GamePlatform::PC
                : $platform;
            $this->saveLinuxFlags(
                $bibId,
                $linuxPlatform,
                !empty($data['tested_on_linux']),
                !empty($data['linux_not_supported'])
            );
            $this->saveNonPretable($bibId, !empty($data['non_pretable']));
            $this->saveOwnedPlatforms($bibId, $ownedFields['owned_platforms']);

            $this->db->commit();

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de l’enregistrement du jeu.';
        }
    }

    /**
     * Met à jour le catalogue d’un jeu (admin).
     *
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalog(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        if (!self::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        $game = $this->findByBibId($bibId, $userId, $foyerId);
        if ($game === null) {
            return 'Jeu introuvable.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        $titreOriginal = trim((string) ($data['titre_original'] ?? ''));
        if (GameTitle::displayTitle(['titre' => $titre, 'titre_original' => $titreOriginal]) === '') {
            return 'Le titre est obligatoire.';
        }

        $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return 'Fiche catalogue introuvable.';
        }

        $platformFields = self::resolveCatalogPlatformFields($data);
        $platform = $platformFields['platform'];
        $platformsCsv = $platformFields['platforms'];
        $ownedFields = self::ownedPlatformsFromPost($data, $platformsCsv);
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');
        $isDigital = !empty($data['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);
        $relationError = self::validateGameRelationFlags($data, $oeuvreId);
        if ($relationError !== null) {
            return $relationError;
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'titre_original' => $titreOriginal,
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
            ], ['titre', 'titre_original', 'annee', 'synopsis']);

            if (self::hasEditionColumns()) {
                $platformsSql = self::hasPlatformsColumn() ? ', platforms = ?' : '';
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET
                        studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?,
                        physical_supports = ?, digital_stores = ?'
                        . $platformsSql
                        . self::igdbMetadataUpdateSet()
                        . GameRelations::updateSet()
                        . ' WHERE oeuvre_id = ?'
                )->execute(array_merge([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                    $physicalSupports,
                    $digitalStores,
                ], self::hasPlatformsColumn() ? [$platformsCsv] : [], self::igdbMetadataWriteParams($data), GameRelations::writeParams($data), [$oeuvreId]));
            } else {
                $platformsSql = self::hasPlatformsColumn() ? ', platforms = ?' : '';
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?'
                    . $platformsSql
                    . self::igdbMetadataUpdateSet()
                    . GameRelations::updateSet()
                    . ' WHERE oeuvre_id = ?'
                )->execute(array_merge([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                ], self::hasPlatformsColumn() ? [$platformsCsv] : [], self::igdbMetadataWriteParams($data), GameRelations::writeParams($data), [$oeuvreId]));
            }

            $linuxPlatform = in_array(GamePlatform::PC, $ownedFields['owned_platform_list'], true)
                ? GamePlatform::PC
                : $platform;
            $this->saveLinuxFlags(
                $bibId,
                $linuxPlatform,
                !empty($data['tested_on_linux']),
                !empty($data['linux_not_supported'])
            );
            $this->saveNonPretable($bibId, !empty($data['non_pretable']));
            $this->saveOwnedPlatforms($bibId, $ownedFields['owned_platforms']);

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de la mise à jour du jeu.';
        }
    }

    /**
     * Met à jour le catalogue d’un jeu à partir de son identifiant œuvre (admin, sans bib_id).
     *
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalogByOeuvreId(int $oeuvreId, array $data): bool|string
    {
        if (!self::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        $game = $this->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return 'Jeu introuvable dans le catalogue.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        $titreOriginal = trim((string) ($data['titre_original'] ?? ''));
        if (GameTitle::displayTitle(['titre' => $titre, 'titre_original' => $titreOriginal]) === '') {
            return 'Le titre est obligatoire.';
        }

        $platformFields = self::resolveCatalogPlatformFields($data);
        $platform = $platformFields['platform'];
        $platformsCsv = $platformFields['platforms'];
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');
        $isDigital = !empty($data['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);
        $relationError = self::validateGameRelationFlags($data, $oeuvreId);
        if ($relationError !== null) {
            return $relationError;
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'titre_original' => $titreOriginal,
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => SecureUrl::sanitizePosterUrl(trim((string) ($data['poster_url'] ?? ''))),
            ], ['titre', 'titre_original', 'annee', 'synopsis', 'poster_url']);

            if (self::hasEditionColumns()) {
                $platformsSql = self::hasPlatformsColumn() ? ', platforms = ?' : '';
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET
                        studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?,
                        physical_supports = ?, digital_stores = ?'
                        . $platformsSql
                        . self::igdbMetadataUpdateSet()
                        . GameRelations::updateSet()
                        . ' WHERE oeuvre_id = ?'
                )->execute(array_merge([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                    $physicalSupports,
                    $digitalStores,
                ], self::hasPlatformsColumn() ? [$platformsCsv] : [], self::igdbMetadataWriteParams($data), GameRelations::writeParams($data), [$oeuvreId]));
            } else {
                $platformsSql = self::hasPlatformsColumn() ? ', platforms = ?' : '';
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?'
                    . $platformsSql
                    . self::igdbMetadataUpdateSet()
                    . GameRelations::updateSet()
                    . ' WHERE oeuvre_id = ?'
                )->execute(array_merge([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                ], self::hasPlatformsColumn() ? [$platformsCsv] : [], self::igdbMetadataWriteParams($data), GameRelations::writeParams($data), [$oeuvreId]));
            }

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de la mise à jour du jeu.';
        }
    }

    /** Résumé supports pour listes (ex. « CD/DVD · Steam »). */
    public static function editionSummary(array $row): string
    {
        return GameRowMapper::editionSummary($row);
    }

    /** Libellé affiché pour autocomplétion (ex. « Elden Ring (PS5 · 2022) »). */
    public static function displayLabel(array $row): string
    {
        return GameRowMapper::displayLabel($row);
    }

    /**
     * Genres déjà utilisés dans le catalogue (pour réutilisation à la saisie).
     *
     * @return list<string>
     */
    public function listKnownGenres(int $limit = 80): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 200));
        $stmt = $this->db->query(
            "SELECT genre FROM oeuvre_jeu WHERE TRIM(genre) != '' ORDER BY genre COLLATE FRENCH_NOCASE ASC"
        );
        if ($stmt === false) {
            return [];
        }

        $known = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach (GameGenre::parseList((string) ($row['genre'] ?? '')) as $tag) {
                $key = mb_strtolower($tag);
                if (!isset($known[$key])) {
                    $known[$key] = $tag;
                }
            }
        }

        $tags = array_values($known);
        sort($tags, SORT_NATURAL | SORT_FLAG_CASE);

        return array_slice($tags, 0, $limit);
    }

    public function updatePosterUrl(int $oeuvreId, string $posterUrl): bool
    {
        if ($oeuvreId <= 0 || !self::isAvailable()) {
            return false;
        }

        $game = $this->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return false;
        }

        (new OeuvreRepository())->update($oeuvreId, [
            'poster_url' => trim($posterUrl),
        ], ['poster_url']);

        return true;
    }

    /**
     * Enregistre la jaquette : fichier upload prioritaire, sinon téléchargement URL → stockage local.
     */
    public function savePoster(int $oeuvreId, string $posterUrlInput, ?string $uploadedBinary = null): void
    {
        if ($oeuvreId <= 0 || !self::isAvailable()) {
            return;
        }

        $storage = new PosterStorage();

        if ($uploadedBinary !== null && $uploadedBinary !== '') {
            $local = $storage->importBinaryForOeuvre($oeuvreId, $uploadedBinary);
            if ($local !== '') {
                $this->updatePosterUrl($oeuvreId, $local);
            }

            return;
        }

        $posterUrlInput = trim($posterUrlInput);
        if ($posterUrlInput === '') {
            return;
        }

        $local = $storage->ensureLocalForOeuvre($oeuvreId, $posterUrlInput);
        if ($local !== '') {
            $this->updatePosterUrl($oeuvreId, $local);

            return;
        }

        $sanitized = SecureUrl::sanitizePosterUrl($posterUrlInput);
        if ($sanitized !== '') {
            $this->updatePosterUrl($oeuvreId, $sanitized);
        }
    }

    private static function selectGameRow(): string
    {
        $edition = GameSchema::hasEditionColumns()
            ? ', oj.physical_supports, oj.digital_stores'
            : '';
        $extension = GameRelations::selectColumns();
        $igdb = GameSchema::hasIgdbColumns() ? ', oj.igdb_id, oj.igdb_enriched_at' : '';
        $igdbMeta = GameSchema::hasIgdbMetadataColumns()
            ? ', oj.franchise, oj.game_mode, oj.theme, oj.alternative_names'
            : '';
        $linux = GameSchema::hasTestedOnLinuxColumn()
            ? ', b.tested_on_linux' . (GameSchema::hasLinuxNotSupportedColumn() ? ', b.linux_not_supported' : '')
            : '';
        $nonPretable = GameSchema::hasNonPretableColumn() ? ', b.non_pretable' : '';
        $ownedPlatforms = GameSchema::hasOwnedPlatformsColumn() ? ', b.owned_platforms' : '';
        $platformsCol = GameSchema::hasPlatformsColumn() ? ', oj.platforms' : '';

        return 'b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.support_physique, b.created_at, b.saga_ordre,'
            . ' o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital' . $platformsCol . $edition . $extension . $igdb . $igdbMeta . $linux . $nonPretable . $ownedPlatforms;
    }

    private static function selectGameHistoryExtras(): string
    {
        return ','
            . ' (SELECT MAX(h.date_vue) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_session,'
            . ' (SELECT MAX(h.note) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id'
            . '    AND h.note IS NOT NULL AND h.note >= 1) AS note_max,'
            . CatalogSchema::foyerAverageNoteSubquery('b.id', ':foyer_id_rating')
            . GameCompletionRepository::selectListExtrasSql();
    }

    private static function selectCatalogRow(): string
    {
        $edition = GameSchema::hasEditionColumns()
            ? ', oj.physical_supports, oj.digital_stores'
            : '';
        $extension = GameRelations::selectColumns();
        $igdb = GameSchema::hasIgdbColumns() ? ', oj.igdb_id, oj.igdb_enriched_at' : '';
        $igdbMeta = GameSchema::hasIgdbMetadataColumns()
            ? ', oj.franchise, oj.game_mode, oj.theme, oj.alternative_names'
            : '';

        $platformsCol = GameSchema::hasPlatformsColumn() ? ', oj.platforms' : '';

        return 'o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital' . $platformsCol . $edition . $extension . $igdb . $igdbMeta;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertCatalogGameRow(
        int $oeuvreId,
        array $data,
        string $platform,
        string $platformsCsv,
        bool $isDigital,
        bool $isExtension,
        int $baseGameOeuvreId
    ): void {
        $data = array_merge($data, [
            'is_extension' => $isExtension,
            'base_game_oeuvre_id' => $baseGameOeuvreId,
        ]);
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');

        if (self::hasEditionColumns()) {
            $platformsInsert = self::hasPlatformsColumn() ? ', platforms' : '';
            $platformsValue = self::hasPlatformsColumn() ? ', ?' : '';
            $this->db->prepare(
                'INSERT INTO oeuvre_jeu (
                    oeuvre_id, studio, editeur, genre, platform, is_digital,
                    physical_supports, digital_stores'
                    . $platformsInsert
                    . GameRelations::insertColumns()
                    . '
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?'
                    . $platformsValue
                    . GameRelations::insertPlaceholders()
                    . ')'
            )->execute(array_merge([
                $oeuvreId,
                trim((string) ($data['studio'] ?? '')),
                trim((string) ($data['editeur'] ?? '')),
                GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                $platform,
                $isDigital ? 1 : 0,
                $physicalSupports,
                $digitalStores,
            ], self::hasPlatformsColumn() ? [$platformsCsv] : [], GameRelations::writeParams($data)));

            return;
        }

        $platformsInsert = self::hasPlatformsColumn() ? ', platforms' : '';
        $platformsValue = self::hasPlatformsColumn() ? ', ?' : '';
        $this->db->prepare(
            'INSERT INTO oeuvre_jeu (
                oeuvre_id, studio, editeur, genre, platform, is_digital'
                . $platformsInsert
                . GameRelations::insertColumns()
                . '
             ) VALUES (?, ?, ?, ?, ?, ?'
                . $platformsValue
                . GameRelations::insertPlaceholders()
                . ')'
        )->execute(array_merge([
            $oeuvreId,
            trim((string) ($data['studio'] ?? '')),
            trim((string) ($data['editeur'] ?? '')),
            GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
            $platform,
            $isDigital ? 1 : 0,
        ], self::hasPlatformsColumn() ? [$platformsCsv] : [], GameRelations::writeParams($data)));
    }

    public static function relationInsertColumns(): string
    {
        return GameRelations::insertColumns();
    }

    public static function relationInsertPlaceholders(): string
    {
        return GameRelations::insertPlaceholders();
    }

    public static function relationUpdateSet(): string
    {
        return GameRelations::updateSet();
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    public static function relationWriteParams(array $data): array
    {
        return GameRelations::writeParams($data);
    }

    public static function formatAddedAt(string $createdAt): string
    {
        return GameRowMapper::formatAddedAt($createdAt);
    }

    /**
     * Conditions SQL OR pour rechercher un jeu (titre, studio, genre, acronymes IGDB).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function gameSearchSqlConditions(
        string $query,
        bool $includeGenre,
        bool $includePrefix,
        string $titleParam = 'q_titre',
    ): array {
        $pattern = SearchMatch::foldedContainsPattern($query);
        $conditions = [
            'fold_search(o.titre) LIKE :' . $titleParam . ' ESCAPE \'\\\'',
            'fold_search(COALESCE(oj.studio, \'\')) LIKE :q_studio ESCAPE \'\\\'',
        ];
        $params = [
            $titleParam => $pattern,
            'q_studio' => $pattern,
        ];

        if ($includeGenre) {
            $conditions[] = 'fold_search(COALESCE(oj.genre, \'\')) LIKE :q_genre ESCAPE \'\\\'';
            $params['q_genre'] = $pattern;
        }

        if (self::hasIgdbMetadataColumns()) {
            $conditions[] = 'fold_search(COALESCE(oj.alternative_names, \'\')) LIKE :q_acronym ESCAPE \'\\\'';
            $params['q_acronym'] = $pattern;
        }

        if ($includePrefix) {
            $prefixPattern = SearchMatch::foldedPrefixPattern($query, 2);
            if ($prefixPattern !== '') {
                $conditions[] = 'fold_search(o.titre) LIKE :q_prefix ESCAPE \'\\\'';
                $conditions[] = 'fold_search(COALESCE(oj.studio, \'\')) LIKE :q_prefix_studio ESCAPE \'\\\'';
                $params['q_prefix'] = $prefixPattern;
                $params['q_prefix_studio'] = $prefixPattern;

                if (self::hasIgdbMetadataColumns()) {
                    $conditions[] = 'fold_search(COALESCE(oj.alternative_names, \'\')) LIKE :q_prefix_acronym ESCAPE \'\\\'';
                    $params['q_prefix_acronym'] = $prefixPattern;
                }
            }
        }

        return ['(' . implode(' OR ', $conditions) . ')', $params];
    }

    private static function igdbMetadataUpdateSet(): string
    {
        return GameSchema::hasIgdbMetadataColumns()
            ? ', franchise = ?, game_mode = ?, theme = ?, alternative_names = ?'
            : '';
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private static function igdbMetadataWriteParams(array $data): array
    {
        if (!GameSchema::hasIgdbMetadataColumns()) {
            return [];
        }

        return [
            trim((string) ($data['franchise'] ?? '')),
            GameGenre::normalizeInput((string) ($data['game_mode'] ?? '')),
            GameGenre::normalizeInput((string) ($data['theme'] ?? '')),
            GameGenre::normalizeInput((string) ($data['alternative_names'] ?? '')),
        ];
    }
}

<?php
/**
 * Jeux vidéo : catalogue (oeuvres + oeuvre_jeu) et collection utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameRepository
{
    /** @return list<string> */
    public static function sortableColumns(): array
    {
        return GameCatalogSql::sortableColumns();
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return GameCatalogSql::isValidSortColumn($sortBy);
    }

    private PDO $db;

    private ?GameLinkedGamesQuery $linkedGamesQuery = null;

    private ?GameCatalogWriter $catalogWriterCache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function linkedGames(): GameLinkedGamesQuery
    {
        return $this->linkedGamesQuery ??= new GameLinkedGamesQuery($this->db);
    }

    private function catalogWriter(): GameCatalogWriter
    {
        return $this->catalogWriterCache ??= new GameCatalogWriter($this->db);
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
        return GameFormPayload::nonPretableFromPost($post);
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
        return GameFormPayload::catalogPlatformsFromPost($post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{owned_platforms: string, owned_platform_list: list<string>}
     */
    public static function ownedPlatformsFromPost(array $post, string $catalogPlatformsCsv): array
    {
        return GameFormPayload::ownedPlatformsFromPost($post, $catalogPlatformsCsv);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{platform: string, platforms: string, platform_list: list<string>}
     */
    public static function resolveCatalogPlatformFields(array $data): array
    {
        return GameFormPayload::resolveCatalogPlatformFields($data);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{physical_supports: string, digital_stores: string, is_digital: bool}
     */
    public static function editionPayloadFromPost(array $post): array
    {
        return GameFormPayload::editionPayloadFromPost($post);
    }

    public static function linuxFlagsFromPost(array $post): array
    {
        return GameFormPayload::linuxFlagsFromPost($post);
    }

    /** @deprecated Utiliser linuxFlagsFromPost() */
    public static function testedOnLinuxFromPost(array $post): bool
    {
        return GameFormPayload::testedOnLinuxFromPost($post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function catalogPayloadFromPost(array $post): array
    {
        return GameFormPayload::catalogPayloadFromPost($post);
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

            $this->catalogWriter()->insertCatalogGameRow(
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
        $orderExpr = GameCatalogSql::sortOrderExpression($sortBy);
        $finishedAtSort = $sortBy === 'finished_at' && GameCompletionRepository::isAvailable();

        $params = [];
        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::normalize($statut));

        $where = [
            'o.media_domain = :game_domain',
            $userWhere,
        ];
        $params['game_domain'] = MediaDomain::JEU;
        $params['history_user_id'] = $userId;

        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            [$searchSql, $searchParams] = GameCatalogSql::gameSearchSqlConditions(
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

        $sql = 'SELECT ' . GameCatalogSql::selectGameRow() . GameCatalogSql::selectGameHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE ' . implode(' AND ', $where);
        if ($finishedAtSort) {
            $sql .= ' ORDER BY derniere_completion IS NULL ASC, derniere_completion ' . $direction;
        } else {
            $sql .= ' ORDER BY ' . $orderExpr . ' ' . $direction;
        }
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

        GameLibraryFields::saveLinuxFlags(
            $this->db,
            $bibId,
            GamePlatform::normalize((string) ($game['platform'] ?? '')),
            $testedOnLinux,
            false
        );

        return true;
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
            [$searchSql, $searchParams] = GameCatalogSql::gameSearchSqlConditions(
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

        $sql = 'SELECT ' . GameCatalogSql::selectCatalogRow()
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
            'SELECT ' . GameCatalogSql::selectGameRow()
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
            'SELECT ' . GameCatalogSql::selectCatalogRow()
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
            GameLibraryFields::saveOwnedPlatforms($this->db, $bibId, $ownedFields['owned_platforms']);
        }

        $ownedList = GamePlatformList::parseList($ownedFields['owned_platforms']);
        if ($ownedList === []) {
            $ownedList = GamePlatformList::catalogKeysFromRow($game);
        }
        $linuxPlatform = in_array(GamePlatform::PC, $ownedList, true)
            ? GamePlatform::PC
            : GamePlatform::normalize((string) ($details['platform'] ?? $game['platform'] ?? ''));
        if (array_key_exists('tested_on_linux', $details) || array_key_exists('linux_not_supported', $details)) {
            GameLibraryFields::saveLinuxFlags(
                $this->db,
                $bibId,
                $linuxPlatform,
                !empty($details['tested_on_linux']),
                !empty($details['linux_not_supported'])
            );
        }

        if (array_key_exists('non_pretable', $details)) {
            GameLibraryFields::saveNonPretable($this->db, $bibId, !empty($details['non_pretable']));
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
            GameCatalogSql::selectCatalogRow(),
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
            GameCatalogSql::selectCatalogRow(),
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

            $this->catalogWriter()->insertCatalogGameRow($oeuvreId, $data, $platform, $platformFields['platforms'], $isDigital, $isExtension, $baseGameOeuvreId);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => trim((string) ($data['support_physique'] ?? '')),
            ]);

            $linuxPlatform = in_array(GamePlatform::PC, $ownedFields['owned_platform_list'], true)
                ? GamePlatform::PC
                : $platform;
            GameLibraryFields::saveLinuxFlags(
                $this->db,
                $bibId,
                $linuxPlatform,
                !empty($data['tested_on_linux']),
                !empty($data['linux_not_supported'])
            );
            GameLibraryFields::saveNonPretable($this->db, $bibId, !empty($data['non_pretable']));
            GameLibraryFields::saveOwnedPlatforms($this->db, $bibId, $ownedFields['owned_platforms']);

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
                        . GameCatalogSql::igdbMetadataUpdateSet()
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
                ], self::hasPlatformsColumn() ? [$platformsCsv] : [], GameCatalogSql::igdbMetadataWriteParams($data), GameRelations::writeParams($data), [$oeuvreId]));
            } else {
                $platformsSql = self::hasPlatformsColumn() ? ', platforms = ?' : '';
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?'
                    . $platformsSql
                    . GameCatalogSql::igdbMetadataUpdateSet()
                    . GameRelations::updateSet()
                    . ' WHERE oeuvre_id = ?'
                )->execute(array_merge([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                ], self::hasPlatformsColumn() ? [$platformsCsv] : [], GameCatalogSql::igdbMetadataWriteParams($data), GameRelations::writeParams($data), [$oeuvreId]));
            }

            $linuxPlatform = in_array(GamePlatform::PC, $ownedFields['owned_platform_list'], true)
                ? GamePlatform::PC
                : $platform;
            GameLibraryFields::saveLinuxFlags(
                $this->db,
                $bibId,
                $linuxPlatform,
                !empty($data['tested_on_linux']),
                !empty($data['linux_not_supported'])
            );
            GameLibraryFields::saveNonPretable($this->db, $bibId, !empty($data['non_pretable']));
            GameLibraryFields::saveOwnedPlatforms($this->db, $bibId, $ownedFields['owned_platforms']);

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
                        . GameCatalogSql::igdbMetadataUpdateSet()
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
                ], self::hasPlatformsColumn() ? [$platformsCsv] : [], GameCatalogSql::igdbMetadataWriteParams($data), GameRelations::writeParams($data), [$oeuvreId]));
            } else {
                $platformsSql = self::hasPlatformsColumn() ? ', platforms = ?' : '';
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?'
                    . $platformsSql
                    . GameCatalogSql::igdbMetadataUpdateSet()
                    . GameRelations::updateSet()
                    . ' WHERE oeuvre_id = ?'
                )->execute(array_merge([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                ], self::hasPlatformsColumn() ? [$platformsCsv] : [], GameCatalogSql::igdbMetadataWriteParams($data), GameRelations::writeParams($data), [$oeuvreId]));
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
}

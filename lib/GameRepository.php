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
        'added_at' => 'b.created_at',
    ];

    /** @return list<string> */
    public static function sortableColumns(): array
    {
        return ['titre', 'annee', 'genre', 'studio', 'support', 'note', 'added_at'];
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return in_array($sortBy, self::sortableColumns(), true);
    }

    private static function sortOrderExpression(string $sortBy): string
    {
        if ($sortBy === 'support') {
            if (self::hasEditionColumns()) {
                return 'oj.physical_supports COLLATE NOCASE, oj.digital_stores COLLATE NOCASE, oj.is_digital';
            }

            return 'oj.is_digital';
        }

        return self::SORT_COLUMNS[$sortBy] ?? self::SORT_COLUMNS['titre'];
    }

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return self::tableExists() && CatalogSchema::usesCatalogTables(Database::getInstance());
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'oeuvre_jeu' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function hasEditionColumns(): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = Database::getInstance()->query('PRAGMA table_info(oeuvre_jeu)');
        if ($stmt === false) {
            return false;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? '') === 'physical_supports') {
                return true;
            }
        }

        return false;
    }

    public static function hasExtensionColumns(): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = Database::getInstance()->query('PRAGMA table_info(oeuvre_jeu)');
        if ($stmt === false) {
            return false;
        }

        $hasIsExtension = false;
        $hasBase = false;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? '') === 'is_extension') {
                $hasIsExtension = true;
            }
            if (($row['name'] ?? '') === 'base_game_oeuvre_id') {
                $hasBase = true;
            }
        }

        return $hasIsExtension && $hasBase;
    }

    public static function hasTestedOnLinuxColumn(): bool
    {
        $stmt = Database::getInstance()->query('PRAGMA table_info(bibliotheque)');
        if ($stmt === false) {
            return false;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? '') === 'tested_on_linux') {
                return true;
            }
        }

        return false;
    }

    public static function hasLinuxNotSupportedColumn(): bool
    {
        $stmt = Database::getInstance()->query('PRAGMA table_info(bibliotheque)');
        if ($stmt === false) {
            return false;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? '') === 'linux_not_supported') {
                return true;
            }
        }

        return false;
    }

    /**
     * Exemplaires / éditions depuis un formulaire POST.
     *
     * @param array<string, mixed> $post
     * @return array{physical_supports: string, digital_stores: string, is_digital: bool}
     */
    public static function editionPayloadFromPost(array $post): array
    {
        $platform = GamePlatform::normalize((string) ($post['platform'] ?? ''));
        $physicalSupports = self::hasEditionColumns()
            ? GamePhysicalSupport::normalizeFromPost($post['physical_supports'] ?? [])
            : '';
        $digitalStores = self::hasEditionColumns()
            ? GameDigitalStore::buildFromPost($post, $platform)
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
        $platform = GamePlatform::normalize((string) ($post['platform'] ?? ''));
        if ($platform !== GamePlatform::PC) {
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
            'annee' => max(0, (int) ($post['annee'] ?? 0)),
            'studio' => trim((string) ($post['studio'] ?? '')),
            'editeur' => trim((string) ($post['editeur'] ?? '')),
            'genre' => GameGenre::normalizeFromPost($post['genres'] ?? []),
            'platform' => GamePlatform::normalize((string) ($post['platform'] ?? '')),
            'synopsis' => trim((string) ($post['synopsis'] ?? '')),
            'poster_url' => SecureUrl::sanitizePosterUrl((string) ($post['poster_url'] ?? '')),
            'is_extension' => !empty($post['is_extension']),
            'base_game_oeuvre_id' => max(0, (int) ($post['base_game_oeuvre_id'] ?? 0)),
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

        $platform = GamePlatform::normalize((string) ($data['platform'] ?? ''));
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));
        if ($isExtension && $baseGameOeuvreId <= 0) {
            return 'Pour une extension, choisissez un jeu de base dans le catalogue.';
        }

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

            $this->insertCatalogGameRow($oeuvreId, $data, $platform, false, $isExtension, $baseGameOeuvreId);

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
            $where[] = '(LOWER(o.titre) LIKE LOWER(:q) ESCAPE \'\\\' OR LOWER(oj.studio) LIKE LOWER(:q_studio) ESCAPE \'\\\' OR LOWER(oj.genre) LIKE LOWER(:q_genre) ESCAPE \'\\\')';
            $params['q'] = LikePattern::containsFragment($searchQuery);
            $params['q_studio'] = LikePattern::containsFragment($searchQuery);
            $params['q_genre'] = LikePattern::containsFragment($searchQuery);
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

        return array_map([$this, 'hydrateGameRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
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
        $params = ['game_domain' => MediaDomain::JEU];
        $where = ['o.media_domain = :game_domain'];

        $query = trim($query);
        if ($query !== '') {
            $where[] = '(LOWER(o.titre) LIKE LOWER(:q) ESCAPE \'\\\' OR LOWER(oj.studio) LIKE LOWER(:q_studio) ESCAPE \'\\\')';
            $params['q'] = LikePattern::containsFragment($query);
            $params['q_studio'] = LikePattern::containsFragment($query);
        }

        $sql = 'SELECT ' . self::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE ASC'
            . ' LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'hydrateCatalogRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
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

        $game = $this->hydrateGameRow($row);
        if (self::hasExtensionColumns()) {
            $baseId = (int) ($game['base_game_oeuvre_id'] ?? 0);
            if (!empty($game['is_extension']) && $baseId > 0) {
                $base = $this->findCatalogByOeuvreId($baseId);
                if ($base !== null) {
                    $game['base_game_label'] = (string) ($base['display_label'] ?? $base['titre'] ?? '');
                    $game['base_game_titre'] = (string) ($base['titre'] ?? '');
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
        if (!self::isAvailable() || !self::hasExtensionColumns() || $baseOeuvreId <= 0) {
            return [];
        }

        $params = [
            'base_id' => $baseOeuvreId,
            'domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ];

        $stmt = $this->db->prepare(
            'SELECT b.id AS bib_id, o.id AS oeuvre_id, o.titre, o.annee, oj.platform'
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.media_domain = :domain'
            . ' AND oj.is_extension = 1'
            . ' AND oj.base_game_oeuvre_id = :base_id'
            . ' AND ('
            . '   (b.statut = :collection AND b.foyer_id = :foyer_id)'
            . '   OR (b.statut = :wishlist AND b.user_id = :user_id)'
            . ' )'
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE ASC, o.annee ASC'
        );
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            $titre = (string) ($row['titre'] ?? '');
            $platformShort = GamePlatform::shortLabel((string) ($row['platform'] ?? ''));
            $annee = (int) ($row['annee'] ?? 0);
            $label = $titre;
            $parts = [];
            if ($platformShort !== '') {
                $parts[] = $platformShort;
            }
            if ($annee > 0) {
                $parts[] = (string) $annee;
            }
            if ($parts !== []) {
                $label = $titre . ' (' . implode(' · ', $parts) . ')';
            }

            $out[] = [
                'bib_id' => (int) ($row['bib_id'] ?? 0),
                'oeuvre_id' => $oeuvreId,
                'titre' => $titre,
                'annee' => $annee,
                'platform_short' => $platformShort,
                'display_label' => $label,
            ];
        }

        return $out;
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

        return $row !== false ? $this->hydrateCatalogRow($row) : null;
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

        $platform = GamePlatform::normalize((string) ($details['platform'] ?? $game['platform'] ?? ''));
        if (array_key_exists('tested_on_linux', $details) || array_key_exists('linux_not_supported', $details)) {
            $this->saveLinuxFlags(
                $bibId,
                $platform,
                !empty($details['tested_on_linux']),
                !empty($details['linux_not_supported'])
            );
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
        if (!self::isAvailable() || !self::hasExtensionColumns() || $baseOeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.media_domain = ? AND oj.is_extension = 1 AND oj.base_game_oeuvre_id = ?'
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE ASC, o.annee ASC'
        );
        $stmt->execute([MediaDomain::JEU, $baseOeuvreId]);

        return array_map([$this, 'hydrateCatalogRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
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
        $platform = GamePlatform::normalize((string) ($data['platform'] ?? ''));
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');
        $isDigital = !empty($data['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, !empty($data['is_digital']));
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));
        if ($isExtension && $baseGameOeuvreId <= 0) {
            return 'Pour une extension, choisissez un jeu de base dans le catalogue.';
        }

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

            $this->insertCatalogGameRow($oeuvreId, $data, $platform, $isDigital, $isExtension, $baseGameOeuvreId);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => trim((string) ($data['support_physique'] ?? '')),
            ]);

            $this->saveLinuxFlags(
                $bibId,
                $platform,
                !empty($data['tested_on_linux']),
                !empty($data['linux_not_supported'])
            );

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
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return 'Fiche catalogue introuvable.';
        }

        $platform = GamePlatform::normalize((string) ($data['platform'] ?? ''));
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');
        $isDigital = !empty($data['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));
        if ($isExtension && $baseGameOeuvreId <= 0) {
            return 'Pour une extension, choisissez un jeu de base dans le catalogue.';
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
            ], ['titre', 'annee', 'synopsis']);

            if (self::hasEditionColumns()) {
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET
                        studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?,
                        physical_supports = ?, digital_stores = ?'
                        . (self::hasExtensionColumns() ? ', is_extension = ?, base_game_oeuvre_id = ?' : '')
                        . ' WHERE oeuvre_id = ?'
                )->execute([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                    $physicalSupports,
                    $digitalStores,
                    ...(self::hasExtensionColumns()
                        ? [$isExtension ? 1 : 0, $isExtension ? $baseGameOeuvreId : null]
                        : []),
                    $oeuvreId,
                ]);
            } else {
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?'
                    . (self::hasExtensionColumns() ? ', is_extension = ?, base_game_oeuvre_id = ?' : '')
                    . ' WHERE oeuvre_id = ?'
                )->execute([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                    ...(self::hasExtensionColumns()
                        ? [$isExtension ? 1 : 0, $isExtension ? $baseGameOeuvreId : null]
                        : []),
                    $oeuvreId,
                ]);
            }

            $this->saveLinuxFlags(
                $bibId,
                $platform,
                !empty($data['tested_on_linux']),
                !empty($data['linux_not_supported'])
            );

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
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $platform = GamePlatform::normalize((string) ($data['platform'] ?? ''));
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');
        $isDigital = !empty($data['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));
        if ($isExtension && $baseGameOeuvreId <= 0) {
            return 'Pour une extension, choisissez un jeu de base dans le catalogue.';
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => SecureUrl::sanitizePosterUrl(trim((string) ($data['poster_url'] ?? ''))),
            ], ['titre', 'annee', 'synopsis', 'poster_url']);

            if (self::hasEditionColumns()) {
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET
                        studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?,
                        physical_supports = ?, digital_stores = ?'
                        . (self::hasExtensionColumns() ? ', is_extension = ?, base_game_oeuvre_id = ?' : '')
                        . ' WHERE oeuvre_id = ?'
                )->execute([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                    $physicalSupports,
                    $digitalStores,
                    ...(self::hasExtensionColumns()
                        ? [$isExtension ? 1 : 0, $isExtension ? $baseGameOeuvreId : null]
                        : []),
                    $oeuvreId,
                ]);
            } else {
                $this->db->prepare(
                    'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?'
                    . (self::hasExtensionColumns() ? ', is_extension = ?, base_game_oeuvre_id = ?' : '')
                    . ' WHERE oeuvre_id = ?'
                )->execute([
                    trim((string) ($data['studio'] ?? '')),
                    trim((string) ($data['editeur'] ?? '')),
                    GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                    $platform,
                    $isDigital ? 1 : 0,
                    ...(self::hasExtensionColumns()
                        ? [$isExtension ? 1 : 0, $isExtension ? $baseGameOeuvreId : null]
                        : []),
                    $oeuvreId,
                ]);
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
        $parts = GamePhysicalSupport::displayLabels((string) ($row['physical_supports'] ?? ''));
        $parts = array_merge($parts, GameDigitalStore::summaryLabels((string) ($row['digital_stores'] ?? '')));

        if ($parts === [] && !empty($row['is_digital'])) {
            $parts[] = 'Démat';
        }

        return implode(' · ', $parts);
    }

    /** Libellé affiché pour autocomplétion (ex. « Elden Ring (PS5 · 2022) »). */
    public static function displayLabel(array $row): string
    {
        $titre = trim((string) ($row['titre'] ?? ''));
        if ($titre === '') {
            return '';
        }

        $parts = [];
        $platform = GamePlatform::shortLabel((string) ($row['platform'] ?? ''));
        if ($platform !== '') {
            $parts[] = $platform;
        }
        $annee = (int) ($row['annee'] ?? 0);
        if ($annee > 0) {
            $parts[] = (string) $annee;
        }
        if ($parts === []) {
            return $titre;
        }

        return $titre . ' (' . implode(' · ', $parts) . ')';
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
        $edition = self::hasEditionColumns()
            ? ', oj.physical_supports, oj.digital_stores'
            : '';
        $extension = self::hasExtensionColumns()
            ? ', oj.is_extension, oj.base_game_oeuvre_id'
            : '';
        $linux = self::hasTestedOnLinuxColumn()
            ? ', b.tested_on_linux' . (self::hasLinuxNotSupportedColumn() ? ', b.linux_not_supported' : '')
            : '';

        return 'b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.support_physique, b.created_at,'
            . ' o.titre, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital' . $edition . $extension . $linux;
    }

    private static function selectGameHistoryExtras(): string
    {
        return ','
            . ' (SELECT MAX(h.date_vue) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_session,'
            . ' (SELECT MAX(h.note) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id'
            . '    AND h.note IS NOT NULL AND h.note >= 1) AS note_max,'
            . CatalogSchema::foyerAverageNoteSubquery('b.id', ':foyer_id_rating');
    }

    private static function selectCatalogRow(): string
    {
        $edition = self::hasEditionColumns()
            ? ', oj.physical_supports, oj.digital_stores'
            : '';
        $extension = self::hasExtensionColumns()
            ? ', oj.is_extension, oj.base_game_oeuvre_id'
            : '';

        return 'o.id AS oeuvre_id, o.titre, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital' . $edition . $extension;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertCatalogGameRow(
        int $oeuvreId,
        array $data,
        string $platform,
        bool $isDigital,
        bool $isExtension,
        int $baseGameOeuvreId
    ): void {
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');

        if (self::hasEditionColumns()) {
            $this->db->prepare(
                'INSERT INTO oeuvre_jeu (
                    oeuvre_id, studio, editeur, genre, platform, is_digital,
                    physical_supports, digital_stores'
                    . (self::hasExtensionColumns() ? ', is_extension, base_game_oeuvre_id' : '')
                    . '
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?'
                    . (self::hasExtensionColumns() ? ', ?, ?' : '')
                    . ')'
            )->execute([
                $oeuvreId,
                trim((string) ($data['studio'] ?? '')),
                trim((string) ($data['editeur'] ?? '')),
                GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                $platform,
                $isDigital ? 1 : 0,
                $physicalSupports,
                $digitalStores,
                ...(self::hasExtensionColumns()
                    ? [$isExtension ? 1 : 0, $isExtension ? $baseGameOeuvreId : null]
                    : []),
            ]);

            return;
        }

        $this->db->prepare(
            'INSERT INTO oeuvre_jeu (
                oeuvre_id, studio, editeur, genre, platform, is_digital'
                . (self::hasExtensionColumns() ? ', is_extension, base_game_oeuvre_id' : '')
                . '
             ) VALUES (?, ?, ?, ?, ?, ?'
                . (self::hasExtensionColumns() ? ', ?, ?' : '')
                . ')'
        )->execute([
            $oeuvreId,
            trim((string) ($data['studio'] ?? '')),
            trim((string) ($data['editeur'] ?? '')),
            GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
            $platform,
            $isDigital ? 1 : 0,
            ...(self::hasExtensionColumns()
                ? [$isExtension ? 1 : 0, $isExtension ? $baseGameOeuvreId : null]
                : []),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateGameRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['oeuvre_id'] = (int) ($row['oeuvre_id'] ?? 0);
        $row['annee'] = (int) ($row['annee'] ?? 0);
        $row['is_digital'] = !empty($row['is_digital']);
        $row['is_extension'] = !empty($row['is_extension']);
        $row['base_game_oeuvre_id'] = (int) ($row['base_game_oeuvre_id'] ?? 0);
        $row['platform_label'] = GamePlatform::label((string) ($row['platform'] ?? ''));
        $row['platform_short'] = GamePlatform::shortLabel((string) ($row['platform'] ?? ''));
        $row['display_label'] = self::displayLabel($row);
        $row['genre_list'] = GameGenre::parseList((string) ($row['genre'] ?? ''));
        $row['genre_label'] = GameGenre::displayLabel((string) ($row['genre'] ?? ''));
        $row['physical_support_list'] = GamePhysicalSupport::parseList((string) ($row['physical_supports'] ?? ''));
        $row['physical_support_labels'] = GamePhysicalSupport::displayLabels((string) ($row['physical_supports'] ?? ''));
        $row['digital_store_list'] = GameDigitalStore::parseStoredList((string) ($row['digital_stores'] ?? ''));
        $row['has_digital_edition'] = GameDigitalStore::hasDigitalEdition(
            (string) ($row['digital_stores'] ?? ''),
            !empty($row['is_digital'])
        );
        $row['edition_summary'] = self::editionSummary($row);
        $row['edition_icon_keys'] = GameEditionIcons::iconKeys($row);
        $row['added_at_label'] = self::formatAddedAt((string) ($row['created_at'] ?? ''));
        $row['is_pc'] = GamePlatform::normalize((string) ($row['platform'] ?? '')) === GamePlatform::PC;
        $row['tested_on_linux'] = !empty($row['tested_on_linux']);
        $row['linux_not_supported'] = !empty($row['linux_not_supported']);
        $row['linux_badge'] = $row['tested_on_linux']
            ? 'supported'
            : ($row['linux_not_supported'] ? 'unsupported' : '');

        return $row;
    }

    public static function formatAddedAt(string $createdAt): string
    {
        $createdAt = trim($createdAt);
        if ($createdAt === '') {
            return '';
        }

        return HistoriqueRepository::formatDateVue(substr($createdAt, 0, 10));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateCatalogRow(array $row): array
    {
        $row['oeuvre_id'] = (int) ($row['oeuvre_id'] ?? 0);
        $row['annee'] = (int) ($row['annee'] ?? 0);
        $row['is_digital'] = !empty($row['is_digital']);
        $row['is_extension'] = !empty($row['is_extension']);
        $row['base_game_oeuvre_id'] = (int) ($row['base_game_oeuvre_id'] ?? 0);
        $row['platform_label'] = GamePlatform::label((string) ($row['platform'] ?? ''));
        $row['platform_short'] = GamePlatform::shortLabel((string) ($row['platform'] ?? ''));
        $row['display_label'] = self::displayLabel($row);
        $row['genre_list'] = GameGenre::parseList((string) ($row['genre'] ?? ''));
        $row['genre_label'] = GameGenre::displayLabel((string) ($row['genre'] ?? ''));
        $row['physical_support_list'] = GamePhysicalSupport::parseList((string) ($row['physical_supports'] ?? ''));
        $row['physical_support_labels'] = GamePhysicalSupport::displayLabels((string) ($row['physical_supports'] ?? ''));
        $row['digital_store_list'] = GameDigitalStore::parseStoredList((string) ($row['digital_stores'] ?? ''));
        $row['has_digital_edition'] = GameDigitalStore::hasDigitalEdition(
            (string) ($row['digital_stores'] ?? ''),
            !empty($row['is_digital'])
        );
        $row['edition_summary'] = self::editionSummary($row);

        return $row;
    }
}

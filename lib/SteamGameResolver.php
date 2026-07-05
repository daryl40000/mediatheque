<?php
/**
 * Rapprochement jeux Steam ↔ catalogue / bibliothèque Moncine.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class SteamGameResolver
{
    /** @var array<int, int> */
    private array $manualAppIdIndex = [];

    /** @var array<int, int> */
    private array $steamAppIdIndex = [];

    /** @var array<int, int> */
    private array $igdbIdIndex = [];

    /** @var array<int, int> */
    private array $storeUrlAppIdIndex = [];

    /** @var array<string, int> */
    private array $libraryTitleIndex = [];

    /** @var array<string, int> */
    private array $catalogTitleIndex = [];

    /** @var array<int, int> */
    private array $libraryBibIndex = [];

    /** @var array<int, int> */
    private array $oeuvreIgdbIndex = [];

    /** @var array<int, array<string, mixed>> */
    private array $libraryRowsByOeuvreId = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public static function forUser(int $userId, int $foyerId): self
    {
        $resolver = new self(Database::getInstance());
        $resolver->buildIndexes($userId, $foyerId);

        return $resolver;
    }

    /**
     * @return array{
     *   oeuvre_id: int,
     *   bib_id: int,
     *   match_type: string,
     *   catalog_igdb_id: int
     * }
     */
    public function resolve(int $appid, int $igdbId, string $steamName): array
    {
        $oeuvreId = 0;
        $matchType = '';

        if ($appid > 0 && isset($this->manualAppIdIndex[$appid])) {
            $oeuvreId = $this->manualAppIdIndex[$appid];
            $matchType = 'manual_map';
        } elseif ($appid > 0 && isset($this->steamAppIdIndex[$appid])) {
            $oeuvreId = $this->steamAppIdIndex[$appid];
            $matchType = 'steam_appid';
        } elseif ($igdbId > 0 && isset($this->igdbIdIndex[$igdbId])) {
            $oeuvreId = $this->igdbIdIndex[$igdbId];
            $matchType = 'igdb_id';
        } elseif ($appid > 0 && isset($this->storeUrlAppIdIndex[$appid])) {
            $oeuvreId = $this->storeUrlAppIdIndex[$appid];
            $matchType = 'steam_url';
        } else {
            foreach (SteamTitleMatch::foldedKeys($steamName) as $key) {
                if (isset($this->libraryTitleIndex[$key])) {
                    $oeuvreId = $this->libraryTitleIndex[$key];
                    $matchType = 'title_library';
                    break;
                }
            }
            if ($oeuvreId <= 0) {
                foreach (SteamTitleMatch::foldedKeys($steamName) as $key) {
                    if (isset($this->catalogTitleIndex[$key])) {
                        $oeuvreId = $this->catalogTitleIndex[$key];
                        $matchType = 'title_catalog';
                        break;
                    }
                }
            }
            if ($oeuvreId <= 0) {
                $fuzzyOeuvreId = $this->resolveFuzzyLibraryMatch($steamName);
                if ($fuzzyOeuvreId > 0) {
                    $oeuvreId = $fuzzyOeuvreId;
                    $matchType = 'title_fuzzy_library';
                }
            }
        }

        return [
            'oeuvre_id' => $oeuvreId,
            'bib_id' => $oeuvreId > 0 ? (int) ($this->libraryBibIndex[$oeuvreId] ?? 0) : 0,
            'match_type' => $matchType,
            'catalog_igdb_id' => $oeuvreId > 0 ? (int) ($this->oeuvreIgdbIndex[$oeuvreId] ?? 0) : 0,
        ];
    }

    private function resolveFuzzyLibraryMatch(string $steamName): int
    {
        $steamName = trim($steamName);
        if ($steamName === '' || $this->libraryRowsByOeuvreId === []) {
            return 0;
        }

        $candidates = [];
        foreach ($this->libraryRowsByOeuvreId as $oeuvreId => $row) {
            if (!SearchMatch::matches(GameTitle::searchText($row), $steamName, 1)) {
                continue;
            }
            $candidates[] = (int) $oeuvreId;
        }

        if (count($candidates) !== 1) {
            return 0;
        }

        return $candidates[0];
    }

    private function buildIndexes(int $userId, int $foyerId): void
    {
        if (!GameRepository::isAvailable()) {
            return;
        }

        if (GameSteamAppIdMapRepository::isAvailable()) {
            $this->manualAppIdIndex = (new GameSteamAppIdMapRepository())->buildAppIdIndex();
        }

        $igdbCols = GameRepository::hasIgdbColumns()
            ? ', oj.igdb_id, oj.alternative_names'
            : '';
        $steamCol = GameSchema::hasSteamAppIdColumn() ? ', oj.steam_appid' : '';
        $editionCol = GameSchema::hasEditionColumns() ? ', oj.digital_stores' : '';

        $stmt = $this->db->query(
            'SELECT o.id AS oeuvre_id, o.titre, o.titre_original'
            . $igdbCols . $steamCol . $editionCol
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.media_domain = ' . $this->quoteLiteral(MediaDomain::JEU)
        );
        if ($stmt === false) {
            return;
        }

        $libraryOeuvreIds = $this->loadLibraryOeuvreIds($userId, $foyerId);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }

            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }

            $steamAppid = (int) ($row['steam_appid'] ?? 0);
            if ($steamAppid > 0) {
                $this->steamAppIdIndex[$steamAppid] = $oeuvreId;
            }

            $storedIgdbId = (int) ($row['igdb_id'] ?? 0);
            if ($storedIgdbId > 0) {
                $this->igdbIdIndex[$storedIgdbId] = $oeuvreId;
                $this->oeuvreIgdbIndex[$oeuvreId] = $storedIgdbId;
            }

            $this->indexStoreUrlAppIds((string) ($row['digital_stores'] ?? ''), $oeuvreId);
            SteamTitleMatch::indexCatalogRow($this->catalogTitleIndex, $row, $oeuvreId);

            if (isset($libraryOeuvreIds[$oeuvreId])) {
                SteamTitleMatch::indexCatalogRow($this->libraryTitleIndex, $row, $oeuvreId);
                $this->libraryRowsByOeuvreId[$oeuvreId] = $row;
            }
        }
    }

    /** @return array<int, true> */
    private function loadLibraryOeuvreIds(int $userId, int $foyerId): array
    {
        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::COLLECTION);
        $params['game_domain'] = MediaDomain::JEU;

        $stmt = $this->db->prepare(
            'SELECT b.id AS bib_id, b.oeuvre_id'
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' WHERE o.media_domain = :game_domain AND ' . $userWhere
        );
        $stmt->execute($params);

        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            $bibId = (int) ($row['bib_id'] ?? 0);
            if ($oeuvreId > 0 && $bibId > 0) {
                $out[$oeuvreId] = true;
                $this->libraryBibIndex[$oeuvreId] = $bibId;
            }
        }

        return $out;
    }

    private function indexStoreUrlAppIds(string $digitalStoresJson, int $oeuvreId): void
    {
        foreach (GameDigitalStore::parseStoredList($digitalStoresJson) as $entry) {
            if (($entry['store'] ?? '') !== GameDigitalStore::STEAM) {
                continue;
            }

            $url = (string) ($entry['url'] ?? '');
            $appid = self::extractAppIdFromStoreUrl($url);
            if ($appid > 0) {
                $this->storeUrlAppIdIndex[$appid] = $oeuvreId;
            }

            $slugTitle = SteamTitleMatch::titleFromStoreUrlSlug($url);
            if ($slugTitle !== '') {
                SteamTitleMatch::indexTitle($this->catalogTitleIndex, $slugTitle, $oeuvreId);
                if (isset($this->libraryBibIndex[$oeuvreId])) {
                    SteamTitleMatch::indexTitle($this->libraryTitleIndex, $slugTitle, $oeuvreId);
                }
            }
        }
    }

    public static function extractAppIdFromStoreUrl(string $url): int
    {
        if (preg_match('#/app/(\d+)#', $url, $matches) === 1) {
            return (int) ($matches[1] ?? 0);
        }

        return 0;
    }

    private function quoteLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}

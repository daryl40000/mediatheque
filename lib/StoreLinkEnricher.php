<?php
/**
 * Enrichissement des liens magasins GOG / Epic sur le catalogue jeux.
 */

declare(strict_types=1);

namespace Moncine;

final class StoreLinkEnricher
{
    public function __construct(
        private readonly OeuvreStoreLinkRepository $links = new OeuvreStoreLinkRepository(),
        private readonly GameRepository $games = new GameRepository(),
        private readonly GogCatalogClient $gog = new GogCatalogClient(),
        private readonly EpicCatalogClient $epic = new EpicCatalogClient(),
        private readonly IgdbStoreLinkResolver $igdbStoreLinks = new IgdbStoreLinkResolver(),
    ) {
    }

    public static function isAvailable(): bool
    {
        return OeuvreStoreLinkRepository::isAvailable() && GameRepository::isAvailable();
    }

    /**
     * @param list<string> $stores
     * @return array{linked: int, pending_review: int, skipped: int, errors: list<string>, stores: array<string, array{best: ?array, confidence: float}>}
     */
    public function enrichOeuvre(int $oeuvreId, array $stores, bool $force = false): array
    {
        $result = [
            'linked' => 0,
            'pending_review' => 0,
            'skipped' => 0,
            'errors' => [],
            'stores' => [],
        ];

        if (!self::isAvailable() || $oeuvreId <= 0) {
            $result['errors'][] = 'Enrichissement magasins indisponible (migration ou catalogue).';

            return $result;
        }

        $catalog = $this->games->findCatalogByOeuvreId($oeuvreId);
        if ($catalog === null) {
            $result['errors'][] = 'Fiche catalogue introuvable.';

            return $result;
        }

        foreach ($this->normalizeStores($stores) as $store) {
            $storeResult = $this->enrichOeuvreForStore($oeuvreId, $catalog, $store, $force);
            $result['stores'][$store] = [
                'best' => $storeResult['best'],
                'confidence' => $storeResult['confidence'],
            ];
            $result['linked'] += $storeResult['linked'];
            $result['pending_review'] += $storeResult['pending_review'];
            $result['skipped'] += $storeResult['skipped'];
            $result['errors'] = array_merge($result['errors'], $storeResult['errors']);
        }

        return $result;
    }

    /**
     * @param list<string> $stores
     * @return array{processed: int, linked: int, pending_review: int, skipped: int, errors: list<string>}
     */
    public function enrichBatch(array $stores, int $limit = MONCINE_ENRICH_BATCH_SIZE, bool $onlyMissing = true, bool $force = false): array
    {
        if (!self::isAvailable()) {
            return [
                'processed' => 0,
                'linked' => 0,
                'pending_review' => 0,
                'skipped' => 0,
                'errors' => ['Migration oeuvre_store_links non appliquée.'],
            ];
        }

        $stores = $this->normalizeStores($stores);
        if ($stores === []) {
            return [
                'processed' => 0,
                'linked' => 0,
                'pending_review' => 0,
                'skipped' => 0,
                'errors' => ['Aucun magasin sélectionné.'],
            ];
        }

        $limit = max(1, $limit);
        $candidates = $this->links->findNeedingEnrichmentAny($stores, $limit, $onlyMissing && !$force);
        $processed = 0;
        $linked = 0;
        $pendingReview = 0;
        $skipped = 0;
        $errors = [];

        foreach ($candidates as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }

            $batch = $this->enrichOeuvre($oeuvreId, $stores, $force);
            $processed++;
            $linked += $batch['linked'];
            $pendingReview += $batch['pending_review'];
            $skipped += $batch['skipped'];
            foreach ($batch['errors'] as $error) {
                if (count($errors) < 20) {
                    $errors[] = $error;
                }
            }

            usleep(300_000);
        }

        return [
            'processed' => $processed,
            'linked' => $linked,
            'pending_review' => $pendingReview,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public function countPending(string $store, bool $onlyMissing = true): int
    {
        return $this->links->countNeedingEnrichment($store, $onlyMissing);
    }

    /**
     * @param array<string, mixed> $catalogRow
     * @param array<string, mixed> $linkRow
     */
    public function syncDigitalStoreFromRow(int $oeuvreId, string $store, array $linkRow): void
    {
        // Les liens catalogue (oeuvre_store_links) ne doivent pas modifier digital_stores (possession).
    }

    /**
     * @param array<string, mixed> $catalogRow
     * @return array{best: ?array<string, mixed>, confidence: float, linked: int, pending_review: int, skipped: int, errors: list<string>}
     */
    private function enrichOeuvreForStore(int $oeuvreId, array $catalogRow, string $store, bool $force): array
    {
        $empty = [
            'best' => null,
            'confidence' => 0.0,
            'linked' => 0,
            'pending_review' => 0,
            'skipped' => 1,
            'errors' => [],
        ];

        $existing = $this->links->find($oeuvreId, $store);
        if ($existing !== null && !empty($existing['manually_verified']) && !$force) {
            return $empty;
        }

        $title = GameTitle::displayTitle($catalogRow);
        $queries = StoreTitleNormalizer::searchQueriesFromRow($catalogRow);
        if ($queries === []) {
            return $empty;
        }

        $candidates = $this->findCandidates($catalogRow, $store, $queries);

        if ($candidates === []) {
            if ($store === GameDigitalStore::EPIC && !IgdbStoreLinkResolver::isAvailable()) {
                $err = $this->epic->getLastError();
                if ($err !== null) {
                    return [
                        ...$empty,
                        'errors' => [$title . ' (epic) : ' . $err . ' Configurez IGDB (page Importer) pour le repli automatique.'],
                    ];
                }
            }

            $err = match ($store) {
                GameDigitalStore::GOG => $this->gog->getLastError(),
                GameDigitalStore::EPIC => null,
                default => null,
            };
            if ($err !== null) {
                return [
                    ...$empty,
                    'errors' => [$title . ' (' . $store . ') : ' . $err],
                ];
            }

            return $empty;
        }

        $igdbDirect = !empty($candidates[0]['from_igdb']);
        $match = $igdbDirect
            ? [
                'best' => $candidates[0],
                'confidence' => !empty($candidates[0]['from_catalog_igdb'])
                    ? StoreLinkMatcher::AUTO_VERIFY_THRESHOLD
                    : max(StoreLinkMatcher::MIN_STORE_THRESHOLD, 0.78),
            ]
            : StoreLinkMatcher::bestMatch($title, $candidates, (int) ($catalogRow['annee'] ?? 0));
        $best = $match['best'];
        $confidence = (float) ($match['confidence'] ?? 0.0);

        if ($best === null || $confidence < StoreLinkMatcher::MIN_STORE_THRESHOLD) {
            return [
                'best' => $best,
                'confidence' => $confidence,
                'linked' => 0,
                'pending_review' => 0,
                'skipped' => 1,
                'errors' => [],
            ];
        }

        $slug = trim((string) ($best['slug'] ?? ''));
        $url = trim((string) ($best['url'] ?? ''));
        if ($url === '') {
            $url = $this->storeUrlFor($store, $slug);
        }
        $autoVerify = $confidence >= StoreLinkMatcher::AUTO_VERIFY_THRESHOLD;

        $this->links->upsert($oeuvreId, $store, [
            'store_slug' => $slug,
            'store_url' => $url,
            'store_title' => trim((string) ($best['title'] ?? '')),
            'match_confidence' => $confidence,
            'manually_verified' => $autoVerify,
        ]);

        if ($autoVerify) {
            return [
                'best' => $best,
                'confidence' => $confidence,
                'linked' => 1,
                'pending_review' => 0,
                'skipped' => 0,
                'errors' => [],
            ];
        }

        return [
            'best' => $best,
            'confidence' => $confidence,
            'linked' => 0,
            'pending_review' => 1,
            'skipped' => 0,
            'errors' => [],
        ];
    }

    /**
     * @param list<string> $queries
     * @return list<array{title: string, slug: string, product_id?: int, url?: string, from_igdb?: bool, from_catalog_igdb?: bool}>
     */
    private function findCandidates(array $catalogRow, string $store, array $queries): array
    {
        $store = GameDigitalStore::normalizeStoreKey($store);

        if ($store === GameDigitalStore::EPIC && IgdbStoreLinkResolver::isAvailable()) {
            $igdbLink = $this->igdbStoreLinks->resolve($catalogRow, $store);
            if ($igdbLink !== null) {
                return [$this->igdbCandidateRow($igdbLink)];
            }
        }

        foreach ($queries as $query) {
            $found = $this->searchStore($store, $query);
            if ($found !== []) {
                return $found;
            }
        }

        if ($store !== GameDigitalStore::EPIC && IgdbStoreLinkResolver::isAvailable()) {
            $igdbLink = $this->igdbStoreLinks->resolve($catalogRow, $store);
            if ($igdbLink !== null) {
                return [$this->igdbCandidateRow($igdbLink)];
            }
        }

        return [];
    }

    /**
     * @param array{title: string, slug: string, url: string, from_catalog_igdb: bool} $igdbLink
     * @return array{title: string, slug: string, url: string, from_igdb: true, from_catalog_igdb: bool}
     */
    private function igdbCandidateRow(array $igdbLink): array
    {
        return [
            'title' => $igdbLink['title'],
            'slug' => $igdbLink['slug'],
            'url' => $igdbLink['url'],
            'from_igdb' => true,
            'from_catalog_igdb' => $igdbLink['from_catalog_igdb'],
        ];
    }

    /**
     * @return list<array{title: string, slug: string, product_id?: int}>
     */
    private function searchStore(string $store, string $query): array
    {
        return match (GameDigitalStore::normalizeStoreKey($store)) {
            GameDigitalStore::GOG => $this->gog->search($query),
            GameDigitalStore::EPIC => $this->epic->search($query),
            default => [],
        };
    }

    private function storeUrlFor(string $store, string $slug): string
    {
        return match (GameDigitalStore::normalizeStoreKey($store)) {
            GameDigitalStore::GOG => GogCatalogClient::storeUrl($slug),
            GameDigitalStore::EPIC => EpicCatalogClient::storeUrl($slug),
            default => '',
        };
    }

    /**
     * @param list<string> $stores
     * @return list<string>
     */
    private function normalizeStores(array $stores): array
    {
        $allowed = [GameDigitalStore::GOG, GameDigitalStore::EPIC];
        $out = [];
        foreach ($stores as $store) {
            $store = GameDigitalStore::normalizeStoreKey((string) $store);
            if ($store !== '' && in_array($store, $allowed, true)) {
                $out[$store] = $store;
            }
        }

        return array_values($out);
    }
}

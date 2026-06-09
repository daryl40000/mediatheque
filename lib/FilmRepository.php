<?php
/**
 * Façade dvdthèque : point d’entrée pour « Mes films », envies, quiz, etc.
 *
 * Délègue à CatalogFilmRepository (schéma actuel : oeuvres + bibliotheque)
 * ou FilmRepositoryLegacy si une très ancienne base n’a pas encore été migrée.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FilmRepository
{
    private PDO $db;

    private FilmRepositoryLegacy|CatalogFilmRepository $engine;

    public function __construct()
    {
        $this->db = Database::getInstance();
        // Choix automatique selon les tables présentes dans moncine.db.
        $this->engine = CatalogSchema::usesCatalogTables($this->db)
            ? new CatalogFilmRepository()
            : new FilmRepositoryLegacy();
    }

    public function usesCatalogModel(): bool
    {
        return $this->engine instanceof CatalogFilmRepository;
    }

    /** @return list<array<string, mixed>> */
    public function findAll(
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        string $kindFilter = '',
        ?int $limit = null,
        int $offset = 0
    ): array {
        if ($this->engine instanceof CatalogFilmRepository) {
            return $this->engine->findAll(
                $sortBy,
                $sortDir,
                $searchQuery,
                LibraryStatut::COLLECTION,
                $kindFilter,
                $limit,
                $offset
            );
        }

        return $this->engine->findAll($sortBy, $sortDir, $searchQuery, $kindFilter, $limit, $offset);
    }

    public function countCollectionFiltered(string $searchQuery = '', string $kindFilter = ''): int
    {
        if ($this->engine instanceof CatalogFilmRepository) {
            return $this->engine->countCollectionFiltered($searchQuery, $kindFilter);
        }

        return $this->engine->countCollectionFiltered($searchQuery, $kindFilter);
    }

    /** Films sur la liste de souhaits (catalogue uniquement). */
    public function findAllWishlist(string $sortBy = 'titre', string $sortDir = 'asc', string $searchQuery = ''): array
    {
        if ($this->engine instanceof CatalogFilmRepository) {
            return $this->engine->findAllWishlist($sortBy, $sortDir, $searchQuery);
        }

        return [];
    }

    /**
     * Film précédent / suivant dans la même liste (tri et recherche que la page d’origine).
     *
     * @return array{
     *   prev_id: int|null,
     *   next_id: int|null,
     *   position: int,
     *   total: int,
     *   in_list: bool
     * }
     */
    public function getFilmNavigation(int $filmId, FilmListContext $context): array
    {
        if ($filmId <= 0) {
            return [
                'prev_id' => null,
                'next_id' => null,
                'position' => 0,
                'total' => 0,
                'in_list' => false,
            ];
        }

        if ($context->isWishlist()) {
            if (!$this->engine instanceof CatalogFilmRepository) {
                return [
                    'prev_id' => null,
                    'next_id' => null,
                    'position' => 0,
                    'total' => 0,
                    'in_list' => false,
                ];
            }
            $films = $this->findAllWishlist(
                $context->sortBy(),
                $context->sortDir(),
                $context->searchQuery()
            );
        } else {
            $films = $this->findAll(
                $context->sortBy(),
                $context->sortDir(),
                $context->searchQuery(),
                $context->kindFilter()
            );
        }

        $ids = [];
        foreach ($films as $row) {
            $ids[] = (int) ($row['id'] ?? 0);
        }

        $total = count($ids);
        $index = array_search($filmId, $ids, true);
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

    public function findAllForExport(): array
    {
        return $this->engine->findAllForExport();
    }

    public function findAllRandomOrder(): array
    {
        return $this->engine->findAllRandomOrder();
    }

    public function count(): int
    {
        return $this->engine->count();
    }

    public function countWishlist(): int
    {
        if ($this->engine instanceof CatalogFilmRepository) {
            return $this->engine->countWishlist();
        }

        return 0;
    }

    public function deleteAll(): void
    {
        $this->engine->deleteAll();
    }

    public function deleteById(int $filmId): bool
    {
        return $this->engine->deleteById($filmId);
    }

    /** @param list<int> $filmIds */
    public function deleteFilms(array $filmIds): int
    {
        return $this->engine->deleteFilms($filmIds);
    }

    public function findById(int $id): ?array
    {
        return $this->engine->findById($id);
    }

    public function findByTitreAndRealisateur(string $titre, string $realisateur): ?array
    {
        return $this->engine->findByTitreAndRealisateur($titre, $realisateur);
    }

    /** @param array<string, mixed> $data */
    public function upsert(array $data): void
    {
        $this->engine->upsertFromExport($data);
    }

    /** @param array<string, mixed> $data */
    public function upsertFromExport(array $data, array $importedColumns = []): void
    {
        $this->engine->upsertFromExport($data, $importedColumns);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns
     */
    public function upsertLibraryFromExport(array $data, array $importedColumns = []): void
    {
        if (!$this->engine instanceof CatalogFilmRepository) {
            $this->engine->upsertFromExport($data, $importedColumns);

            return;
        }

        $this->engine->upsertLibraryFromExport($data, $importedColumns);
    }

    public function countLibraryEntries(): int
    {
        if ($this->engine instanceof CatalogFilmRepository) {
            return $this->engine->countLibraryEntries();
        }

        return $this->engine->count();
    }

    /** @return list<string> */
    public function distinctSagas(): array
    {
        return $this->engine->distinctSagas();
    }

    public function listSagasWithCounts(): array
    {
        return $this->engine->listSagasWithCounts();
    }

    public function findBySaga(string $saga): array
    {
        return $this->engine->findBySaga($saga);
    }

    /** @param list<int> $filmIds */
    public function assignFilmsToSaga(array $filmIds, string $saga, int $startOrder = 1): int
    {
        return $this->engine->assignFilmsToSaga($filmIds, $saga, $startOrder);
    }

    public function renameSaga(string $oldName, string $newName): array
    {
        return $this->engine->renameSaga($oldName, $newName);
    }

    /** @param list<int> $filmIds */
    public function updateFilmsSupportPhysique(array $filmIds, string $supportKey): int
    {
        return $this->engine->updateFilmsSupportPhysique($filmIds, $supportKey);
    }

    public function findBySupportPhysique(string $supportKey): array
    {
        return $this->engine->findBySupportPhysique($supportKey);
    }

    /** @return list<string> */
    public function distinctSupportPhysique(): array
    {
        return $this->engine->distinctSupportPhysique();
    }

    /** @return list<string> */
    public function distinctNationalites(): array
    {
        return $this->engine->distinctNationalites();
    }

    /** @return list<string> */
    public function distinctStyles(): array
    {
        return $this->engine->distinctStyles();
    }

    public function countNeedingEnrichment(bool $includeAttempted = false): int
    {
        return $this->engine->countNeedingEnrichment($includeAttempted);
    }

    public function findNeedingEnrichment(int $limit = 10, bool $force = false): array
    {
        return $this->engine->findNeedingEnrichment($limit, $force);
    }

    public function updateEnrichmentMetadata(int $filmId, array $meta, bool $forceReplace = false): void
    {
        $this->engine->updateEnrichmentMetadata($filmId, $meta, $forceReplace);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function updateOeuvreEnrichmentMetadata(int $oeuvreId, array $meta, bool $forceReplace = false): void
    {
        if ($this->engine instanceof CatalogFilmRepository) {
            $this->engine->updateOeuvreEnrichmentMetadata($oeuvreId, $meta, $forceReplace);
        }
    }

    public function markOeuvreEnrichmentAttempt(int $oeuvreId): void
    {
        if ($this->engine instanceof CatalogFilmRepository) {
            $this->engine->markOeuvreEnrichmentAttempt($oeuvreId);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateOeuvreManual(int $oeuvreId, array $data): bool|string
    {
        if ($this->engine instanceof CatalogFilmRepository) {
            return $this->engine->updateOeuvreManual($oeuvreId, $data);
        }

        return 'Le catalogue partagé n’est pas disponible.';
    }

    public function findByTmdbId(int $tmdbId): ?array
    {
        return $this->engine->findByTmdbId($tmdbId);
    }

    public function findByPersonne(string $query): array
    {
        return $this->engine->findByPersonne($query);
    }

    /** @return list<string> */
    public function distinctPersonnes(int $limit = 300): array
    {
        return $this->engine->distinctPersonnes($limit);
    }

    public function updateManual(int $filmId, array $data): bool|string
    {
        return $this->engine->updateManual($filmId, $data);
    }

    public function markEnrichmentAttempt(int $filmId, bool $found): void
    {
        $this->engine->markEnrichmentAttempt($filmId, $found);
    }

    /**
     * Ajoute un film (collection ou wishlist selon le modèle catalogue).
     *
     * @param array<string, mixed> $data
     * @return int|string
     */
    public function createManual(array $data, string $statut): int|string
    {
        return $this->engine->createManual($data, $statut);
    }

    /**
     * Ajoute une œuvre du catalogue partagé à Mes films ou Mes envies en un clic.
     *
     * @return int|string ID bibliothèque ou message d’erreur
     */
    public function addFromCatalogOeuvre(int $oeuvreId, string $statut): int|string
    {
        if (!$this->engine instanceof CatalogFilmRepository) {
            return 'Cette action nécessite le catalogue partagé Moncine.';
        }

        return $this->engine->addFromCatalogOeuvre($oeuvreId, $statut);
    }

    /**
     * Recherche dans le catalogue partagé (autocomplétion titre).
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalogOeuvres(string $query, int $limit = 20): array
    {
        if ($this->engine instanceof CatalogFilmRepository) {
            return $this->engine->searchCatalogOeuvres($query, $limit);
        }

        return [];
    }

    /**
     * Passe un film de la wishlist à la collection.
     *
     * @param int $wishlistTargetId ID d’une ligne wishlist_targets (support + EAN pré-remplis).
     */
    public function promoteToCollection(
        int $libraryId,
        string $supportKey = '',
        string $ean = '',
        ?int $wishlistTargetId = null
    ): bool {
        if ($this->engine instanceof CatalogFilmRepository) {
            return $this->engine->promoteToCollection($libraryId, $supportKey, $ean, $wishlistTargetId);
        }

        return false;
    }

    /** @param array<string, mixed> $post */
    public static function parseBulkFilmIds(array $post): array
    {
        return FilmRepositoryLegacy::parseBulkFilmIds($post);
    }

    public static function formatSagaOrdre(int $ordre): string
    {
        return FilmRepositoryLegacy::formatSagaOrdre($ordre);
    }

    public static function formatSupport(?string $key): string
    {
        return FilmRepositoryLegacy::formatSupport($key);
    }

    public static function formatNationalite(?string $nationalite): string
    {
        return FilmRepositoryLegacy::formatNationalite($nationalite);
    }

    /** @param array<string, mixed> $film */
    public static function rolesForPerson(array $film, string $query): array
    {
        return FilmRepositoryLegacy::rolesForPerson($film, $query);
    }

    public static function formatAnnee(int $annee): string
    {
        return FilmRepositoryLegacy::formatAnnee($annee);
    }

    public static function formatDuree(int $minutes): string
    {
        return FilmRepositoryLegacy::formatDuree($minutes);
    }

    public static function splitStyles(string $styles): array
    {
        return FilmRepositoryLegacy::splitStyles($styles);
    }
}

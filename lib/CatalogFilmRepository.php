<?php
/**
 * Lecture et écriture des films via catalogue (œuvres + bibliothèque utilisateur).
 *
 * Façade : délègue à des classes spécialisées (requêtes, sagas, enrichissement, import, création…)
 * instanciées à la demande et mises en cache (mêmes principes que BdRepository / GameRepository).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogFilmRepository
{
    private PDO $db;

    private ?OeuvreRepository $oeuvresCache = null;

    private ?BibliothequeRepository $bibliothequeCache = null;

    private ?FilmLibraryQuery $libraryQueryCache = null;

    private ?FilmPersonQuery $personQueryCache = null;

    private ?FilmCatalogSaga $sagaCache = null;

    private ?FilmLibraryMutations $mutationsCache = null;

    private ?FilmPosterService $posterServiceCache = null;

    private ?FilmCatalogEnrichment $enrichmentCache = null;

    private ?FilmCatalogImport $importCache = null;

    private ?FilmCatalogUpdater $updaterCache = null;

    private ?FilmLibraryAttach $libraryAttachCache = null;

    private ?FilmCatalogCreator $catalogCreatorCache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function oeuvres(): OeuvreRepository
    {
        return $this->oeuvresCache ??= new OeuvreRepository();
    }

    private function bibliotheque(): BibliothequeRepository
    {
        return $this->bibliothequeCache ??= new BibliothequeRepository();
    }

    private function libraryQuery(): FilmLibraryQuery
    {
        return $this->libraryQueryCache ??= new FilmLibraryQuery($this->db, $this->oeuvres(), $this->bibliotheque());
    }

    private function personQuery(): FilmPersonQuery
    {
        return $this->personQueryCache ??= new FilmPersonQuery($this->db);
    }

    private function saga(): FilmCatalogSaga
    {
        return $this->sagaCache ??= new FilmCatalogSaga($this->db);
    }

    private function mutations(): FilmLibraryMutations
    {
        return $this->mutationsCache ??= new FilmLibraryMutations($this->db, $this->bibliotheque());
    }

    private function posterService(): FilmPosterService
    {
        return $this->posterServiceCache ??= new FilmPosterService($this->oeuvres());
    }

    private function enrichment(): FilmCatalogEnrichment
    {
        return $this->enrichmentCache ??= new FilmCatalogEnrichment(
            $this->db,
            $this->oeuvres(),
            $this->libraryQuery(),
            $this->posterService()
        );
    }

    private function import(): FilmCatalogImport
    {
        return $this->importCache ??= new FilmCatalogImport(
            $this->oeuvres(),
            $this->bibliotheque(),
            $this->libraryQuery(),
            $this->posterService()
        );
    }

    private function updater(): FilmCatalogUpdater
    {
        return $this->updaterCache ??= new FilmCatalogUpdater(
            $this->oeuvres(),
            $this->bibliotheque(),
            $this->libraryQuery(),
            $this->posterService(),
            $this->saga()
        );
    }

    private function libraryAttach(): FilmLibraryAttach
    {
        return $this->libraryAttachCache ??= new FilmLibraryAttach(
            $this->oeuvres(),
            $this->bibliotheque(),
            $this->updater(),
            $this->saga()
        );
    }

    private function catalogCreator(): FilmCatalogCreator
    {
        return $this->catalogCreatorCache ??= new FilmCatalogCreator(
            $this->oeuvres(),
            $this->import(),
            $this->libraryAttach(),
            $this->updater(),
            $this->libraryQuery()
        );
    }

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
        return $this->libraryQuery()->findAll($sortBy, $sortDir, $searchQuery, $statut, $kindFilter, $limit, $offset);
    }

    public function countCollectionFiltered(
        string $searchQuery = '',
        string $kindFilter = '',
        string $statut = LibraryStatut::COLLECTION
    ): int {
        return $this->libraryQuery()->countCollectionFiltered($searchQuery, $kindFilter, $statut);
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
        return $this->libraryQuery()->findAllWishlist($sortBy, $sortDir, $searchQuery, $kindFilter);
    }

    /** Films + dernière vision et note (pour export), collection uniquement. */
    public function findAllForExport(): array
    {
        return $this->libraryQuery()->findAllForExport();
    }

    /**
     * Collection + envies pour export bibliothèque (léger).
     *
     * @return list<array<string, mixed>>
     */
    public function findAllLibraryForExport(): array
    {
        return $this->libraryQuery()->findAllLibraryForExport();
    }

    public function countLibraryEntries(): int
    {
        return $this->libraryQuery()->countLibraryEntries();
    }

    /** Même liste collection, ordre aléatoire. */
    public function findAllRandomOrder(): array
    {
        return $this->libraryQuery()->findAllRandomOrder();
    }

    public function count(): int
    {
        return $this->libraryQuery()->count();
    }

    public function countWishlist(): int
    {
        return $this->libraryQuery()->countWishlist();
    }

    public function deleteAll(): void
    {
        $this->mutations()->deleteAll();
    }

    public function deleteById(int $filmId): bool
    {
        return $this->mutations()->deleteById($filmId);
    }

    /**
     * @param list<int> $filmIds
     */
    public function deleteFilms(array $filmIds): int
    {
        return $this->mutations()->deleteFilms($filmIds);
    }

    public function findById(int $id): ?array
    {
        return $this->libraryQuery()->findById($id);
    }

    public function findByTitreAndRealisateur(string $titre, string $realisateur): ?array
    {
        return $this->libraryQuery()->findByTitreAndRealisateur($titre, $realisateur);
    }

    /**
     * Suggestions catalogue pour l’autocomplétion du titre (ajout film).
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalogOeuvres(string $query, int $limit = 20): array
    {
        return $this->libraryQuery()->searchCatalogOeuvres($query, $limit);
    }

    /**
     * @param array<string, mixed> $oeuvre
     */
    public static function formatCatalogOeuvreLabel(array $oeuvre): string
    {
        return FilmLibraryQuery::formatCatalogOeuvreLabel($oeuvre);
    }

    /**
     * Import bibliothèque légère : lie une œuvre du catalogue à l’utilisateur (collection ou envies).
     *
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns
     */
    public function upsertLibraryFromExport(array $data, array $importedColumns = []): void
    {
        $this->import()->upsertLibraryFromExport($data, $importedColumns);
    }

    public function upsertFromExport(array $data, array $importedColumns = []): void
    {
        $this->import()->upsertFromExport($data, $importedColumns);
    }

    /** @return list<string> */
    public function distinctSagas(): array
    {
        return $this->saga()->distinctSagas();
    }

    /**
     * Sagas déjà utilisées dans le catalogue (autocomplétion des formulaires).
     *
     * @return list<string>
     */
    public function listKnownSagas(int $limit = 120): array
    {
        return $this->saga()->listKnownSagas($limit);
    }

    /**
     * @return list<array{saga: string, film_count: int}>
     */
    public function listSagasWithCounts(): array
    {
        return $this->saga()->listSagasWithCounts();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findBySaga(string $saga): array
    {
        return $this->libraryQuery()->findBySaga($saga);
    }

    /**
     * Tous les films catalogue d’une saga (tri saga_ordre, titre), hors œuvre exclue.
     *
     * @return list<array{oeuvre_id: int, titre: string, annee: int, poster_url: string|null, saga_ordre: int}>
     */
    public function listCatalogBySaga(string $saga, int $excludeOeuvreId = 0): array
    {
        return $this->saga()->listCatalogBySaga($saga, $excludeOeuvreId);
    }

    /**
     * @param list<int> $filmIds
     */
    public function assignFilmsToSaga(array $filmIds, string $saga, int $startOrder = 1): int
    {
        return $this->saga()->assignFilmsToSaga($filmIds, $saga, $startOrder);
    }

    /**
     * @return array{ok: true, updated: int}|array{ok: false, error: string}
     */
    public function renameSaga(string $oldName, string $newName): array
    {
        return $this->saga()->renameSaga($oldName, $newName);
    }

    /**
     * @param list<int> $filmIds
     */
    public function updateFilmsSupportPhysique(array $filmIds, string $supportKey): int
    {
        return $this->mutations()->updateFilmsSupportPhysique($filmIds, $supportKey);
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
        return $this->libraryQuery()->findBySupportPhysique($supportKey);
    }

    /** @return list<string> */
    public function distinctSupportPhysique(): array
    {
        return $this->libraryQuery()->distinctSupportPhysique();
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
        return $this->libraryQuery()->distinctNationalites();
    }

    /** @return list<string> */
    public function distinctStyles(): array
    {
        return $this->libraryQuery()->distinctStyles();
    }

    public function countNeedingEnrichment(bool $includeAttempted = false): int
    {
        return $this->enrichment()->countNeedingEnrichment($includeAttempted);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findNeedingEnrichment(int $limit = 10, bool $force = false): array
    {
        return $this->enrichment()->findNeedingEnrichment($limit, $force);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function updateEnrichmentMetadata(int $filmId, array $meta, bool $forceReplace = false): void
    {
        $this->enrichment()->updateEnrichmentMetadata($filmId, $meta, $forceReplace);
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
        $this->enrichment()->updateOeuvreEnrichmentMetadata($oeuvreId, $meta, $forceReplace, $filmRow);
    }

    public function findByTmdbId(int $tmdbId): ?array
    {
        return $this->libraryQuery()->findByTmdbId($tmdbId);
    }

    /**
     * Films du catalogue partagé où la personne apparaît (réalisateur ou acteur),
     * avec indication collection / envies / absent de la bibliothèque.
     *
     * @return list<array<string, mixed>>
     */
    public function findByPersonne(string $query): array
    {
        return $this->personQuery()->findByPersonne($query);
    }

    /** @return list<string> */
    public function distinctPersonnes(int $limit = 300): array
    {
        return $this->personQuery()->distinctPersonnes($limit);
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
     * Met à jour uniquement l’exemplaire personnel (bibliothèque), pas le catalogue partagé.
     *
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateManual(int $filmId, array $data): bool|string
    {
        return $this->updater()->updateManual($filmId, $data);
    }

    public function markEnrichmentAttempt(int $filmId, bool $found): void
    {
        $this->enrichment()->markEnrichmentAttempt($filmId);
    }

    public function markOeuvreEnrichmentAttempt(int $oeuvreId): void
    {
        $this->enrichment()->markOeuvreEnrichmentAttempt($oeuvreId);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateOeuvreManual(int $oeuvreId, array $data): bool|string
    {
        return $this->updater()->updateOeuvreManual($oeuvreId, $data);
    }

    /**
     * Ajoute une œuvre déjà au catalogue à la bibliothèque (sans formulaire détaillé).
     *
     * @return int|string ID bibliothèque ou message d’erreur
     */
    public function addFromCatalogOeuvre(int $oeuvreId, string $statut): int|string
    {
        return $this->libraryAttach()->addFromCatalogOeuvre($oeuvreId, $statut);
    }

    /**
     * Crée un film dans la bibliothèque (collection ou wishlist).
     *
     * @param array<string, mixed> $data Champs formulaire (comme updateManual)
     * @return int|string ID bibliothèque si OK, sinon message d’erreur
     */
    public function createManual(array $data, string $statut): int|string
    {
        return $this->catalogCreator()->createManual($data, $statut);
    }

    public function promoteToCollection(
        int $libraryId,
        string $supportKey = '',
        string $ean = '',
        ?int $wishlistTargetId = null
    ): bool {
        return $this->mutations()->promoteToCollection($libraryId, $supportKey, $ean, $wishlistTargetId);
    }

    /** @return list<string> */
    public static function splitStyles(string $styles): array
    {
        return FilmRepository::splitStyles($styles);
    }
}

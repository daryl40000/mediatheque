<?php
/**
 * Import/export du catalogue et de la bibliothèque films (ODS/CSV).
 */

declare(strict_types=1);

namespace Moncine;

final class FilmCatalogImport
{
    /** Champs de l’exemplaire personnel exportés/importés (hors statut, géré séparément). */
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

    public function __construct(
        private readonly OeuvreRepository $oeuvres,
        private readonly BibliothequeRepository $bibliotheque,
        private readonly FilmLibraryQuery $libraryQuery,
        private readonly FilmPosterService $posterService
    ) {
    }

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
            // ID absolu (tous domaines) : ne pas filtrer par l’onglet Films/BD/Jeux actif.
            $oeuvre = $this->oeuvres->findByIdForAdmin($oeuvreId);
            if ($oeuvre === null) {
                // Repli : catalogue réimporté avec de nouveaux numéros, mais titre encore présent.
                $oeuvre = $this->findOeuvreByTitreFromImport($data);
                if ($oeuvre === null) {
                    throw new \RuntimeException(
                        'ID catalogue ' . $oeuvreId . ' introuvable. '
                        . 'Réimportez d’abord le CSV catalogue admin (avec les mêmes ID), '
                        . 'en cochant « Réinitialiser le catalogue avant import », '
                        . 'puis réessayez l’import bibliothèque.'
                    );
                }
                $oeuvreId = (int) $oeuvre['id'];
            }

            $library = $this->bibliotheque->findByOeuvreId($oeuvreId, $this->userId(), $this->foyerId());
            if ($library !== null) {
                $this->applyLibraryImportUpdate((int) $library['id'], $data, $importedColumns, $statut);
                $this->ensureDomainLibraryLinks($oeuvre, $statut);

                return;
            }

            $this->attachNewLibraryEntry($oeuvreId, $oeuvre, $data, $statut);

            return;
        }

        if ($libraryId > 0) {
            $existing = $this->libraryQuery->findById($libraryId);
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

        $oeuvre = $this->findOeuvreByTitreFromImport($data);
        if ($oeuvre === null) {
            throw new \RuntimeException(
                'Aucune œuvre « ' . $titre . ' » au catalogue. Utilisez l’ID catalogue ou importez le catalogue.'
            );
        }

        $resolvedId = (int) $oeuvre['id'];
        $library = $this->bibliotheque->findByOeuvreId($resolvedId, $this->userId(), $this->foyerId());
        if ($library !== null) {
            $this->applyLibraryImportUpdate((int) $library['id'], $data, $importedColumns, $statut);
            $this->ensureDomainLibraryLinks($oeuvre, $statut);

            return;
        }

        $this->attachNewLibraryEntry($resolvedId, $oeuvre, $data, $statut);
    }

    /**
     * Ajoute une entrée bibliothèque selon le domaine (BD → série + tome, etc.).
     *
     * @param array<string, mixed> $oeuvre
     * @param array<string, mixed> $data
     */
    private function attachNewLibraryEntry(int $oeuvreId, array $oeuvre, array $data, string $statut): void
    {
        $domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));
        $details = $this->libraryPayloadFromImport($data, $statut);

        if ($domain === MediaDomain::BD && BdRepository::isAvailable()) {
            $result = (new BdRepository())->addFromCatalogOeuvre(
                $oeuvreId,
                $statut,
                $this->userId(),
                $this->foyerId(),
                $details
            );
            if (!is_int($result)) {
                throw new \RuntimeException((string) $result);
            }

            // Compléter les champs bibliothèque (support, etc.) après le rattachement BD.
            $this->bibliotheque->update($result, $details);

            return;
        }

        if ($domain === MediaDomain::JEU && GameRepository::isAvailable()) {
            $result = (new GameRepository())->addFromCatalogOeuvre(
                $oeuvreId,
                $statut,
                $this->userId(),
                $this->foyerId(),
                $details
            );
            if (!is_int($result)) {
                throw new \RuntimeException((string) $result);
            }
            $this->bibliotheque->update($result, $details);

            return;
        }

        if ($domain === MediaDomain::MAGAZINE && MagazineRepository::isAvailable()) {
            $result = (new MagazineRepository())->addFromCatalogOeuvre(
                $oeuvreId,
                $statut,
                $this->userId(),
                $this->foyerId()
            );
            if (!is_int($result)) {
                throw new \RuntimeException((string) $result);
            }
            $this->bibliotheque->update($result, $details);

            return;
        }

        $this->bibliotheque->insert($this->userId(), $this->foyerId(), $oeuvreId, $details);
    }

    /**
     * Pour une entrée déjà présente : s’assure que les liens série (BD / magazine) existent.
     *
     * @param array<string, mixed> $oeuvre
     */
    private function ensureDomainLibraryLinks(array $oeuvre, string $statut): void
    {
        $domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));
        $oeuvreId = (int) ($oeuvre['id'] ?? 0);
        if ($oeuvreId <= 0) {
            return;
        }

        if ($domain === MediaDomain::BD && BdRepository::isAvailable()) {
            $bdRepo = new BdRepository();
            $catalog = $bdRepo->findCatalogByOeuvreId($oeuvreId);
            $seriesId = (int) ($catalog['series_id'] ?? 0);
            if ($seriesId > 0) {
                $bdRepo->registerSeriesInLibrary($seriesId, $statut, $this->userId(), $this->foyerId());
            }
        }
    }

    /**
     * Rapprochement par titre (+ réalisateur) dans le domaine média de la ligne, sinon l’onglet actif.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function findOeuvreByTitreFromImport(array $data): ?array
    {
        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return null;
        }

        $realisateur = trim((string) ($data['realisateur'] ?? ''));
        $domain = trim((string) ($data['media_domain'] ?? ''));
        if ($domain !== '') {
            return $this->oeuvres->findByTitreRealisateurAndDomain(
                $titre,
                $realisateur,
                MediaDomain::normalize($domain)
            );
        }

        // Essayer d’abord le domaine actif, puis sans filtre (tous médias).
        $found = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
        if ($found !== null) {
            return $found;
        }

        return $this->oeuvres->findByTitreRealisateurAnyDomain($titre, $realisateur);
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
        $existing = $this->libraryQuery->findByTitreAndRealisateur(
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
            $this->posterService->cacheOeuvrePosterIfRemote($oeuvreId, (string) ($oeuvrePayload['poster_url'] ?? ''));
            $this->bibliotheque->insert($this->userId(), $this->foyerId(), $oeuvreId, $libraryPayload);

            return;
        }

        if ($existing === null && $oeuvreExisting !== null) {
            $oeuvreId = (int) $oeuvreExisting['id'];
            $oeuvreMerge = $this->resolveOeuvreMergeFields($importedColumns);
            if ($oeuvreMerge !== []) {
                $this->oeuvres->update($oeuvreId, $oeuvrePayload, $oeuvreMerge);
            }
            $this->posterService->cacheOeuvrePosterIfRemote($oeuvreId, (string) ($oeuvrePayload['poster_url'] ?? ''));
            $this->bibliotheque->insert($this->userId(), $this->foyerId(), $oeuvreId, $libraryPayload);

            return;
        }

        $libraryId = (int) $existing['id'];
        $oeuvreId = (int) $existing['oeuvre_id'];

        $oeuvreMerge = $this->resolveOeuvreMergeFields($importedColumns);
        if ($oeuvreMerge !== []) {
            $this->oeuvres->update($oeuvreId, $oeuvrePayload, $oeuvreMerge);
            $this->posterService->cacheOeuvrePosterIfRemote($oeuvreId, (string) ($oeuvrePayload['poster_url'] ?? ''));
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
     * @param array<string, mixed> $oeuvrePayload
     * @param array<string, mixed> $importRow
     */
    private function insertOeuvreFromImport(array $oeuvrePayload, array $importRow): int
    {
        $complete = CatalogSchema::completeOeuvrePayload($oeuvrePayload);
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

    private function userId(): int
    {
        return UserContext::currentUserId();
    }

    private function foyerId(): int
    {
        return UserContext::currentFoyerId();
    }
}

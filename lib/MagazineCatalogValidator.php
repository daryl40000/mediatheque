<?php
declare(strict_types=1);
namespace Moncine;
final class MagazineCatalogValidator { public function __construct(private readonly MagazineLibraryQuery $libraryQuery) {}
    public function horsSerieFromData(array $data, array $issue): bool
    {
        if (!array_key_exists('est_hors_serie', $data)) {
            return !empty($issue['est_hors_serie']);
        }

        if (is_bool($data['est_hors_serie'])) {
            return $data['est_hors_serie'];
        }

        return FormCheckbox::isChecked(['est_hors_serie' => $data['est_hors_serie']], 'est_hors_serie');
    }

    /** Évite les collisions sur UNIQUE (titre, réalisateur) lors d’une mise à jour catalogue. */
    public function validateCatalogIssueTitleUnique(string $titre, int $oeuvreId): ?string
    {
        $existing = (new OeuvreRepository())->findByTitreRealisateurAndDomain(
            $titre,
            '',
            MediaDomain::MAGAZINE
        );
        if ($existing !== null && (int) ($existing['id'] ?? 0) !== $oeuvreId) {
            return 'Une autre fiche catalogue utilise déjà le titre « ' . $titre
                . ' » (œuvre #' . (int) $existing['id']
                . '). Fusionnez les doublons depuis Maintenance catalogue.';
        }

        return null;
    }
    public function validateNumeroForSeries(
        int $seriesId,
        string $numero,
        bool $horsSerie,
        ?int $excludeOeuvreId = null
    ): ?string {
        if ($this->libraryQuery->findCatalogIssueBySeriesNumero($seriesId, $numero, $horsSerie, $excludeOeuvreId) !== null) {
            return $horsSerie
                ? 'Un autre hors-série avec ce numéro existe déjà pour cette revue.'
                : 'Ce numéro existe déjà pour cette série.';
        }

        return null;
    }
}

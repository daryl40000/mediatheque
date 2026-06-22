<?php
/**
 * Export JSON du catalogue magazines (symétrique à l’import ABM).
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineCatalogExporter
{
    /**
     * @param list<string> $titleFilters sous-chaînes de titre de série (vide = tout)
     * @return array<string, mixed>
     */
    public function exportToArray(array $titleFilters = []): array
    {
        if (!MagazineRepository::isAvailable()) {
            return [
                'format_version' => 1,
                'source' => 'mediatheque-catalog-export',
                'generated_at' => gmdate('c'),
                'stats' => ['series_count' => 0, 'issue_count' => 0],
                'series' => [],
            ];
        }

        $titleFilters = $this->normalizeTitleFilters($titleFilters);
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT s.* FROM series s
             WHERE s.media_domain = ?
             ORDER BY s.titre COLLATE FRENCH_NOCASE ASC'
        );
        $stmt->execute([MediaDomain::MAGAZINE]);
        $seriesRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $magRepo = new MagazineRepository();
        $exportSeries = [];
        $issueCount = 0;

        foreach ($seriesRows as $seriesRow) {
            $titre = trim((string) ($seriesRow['titre'] ?? ''));
            if ($titre === '') {
                continue;
            }
            if ($titleFilters !== [] && !$this->titleMatchesFilters($titre, $titleFilters)) {
                continue;
            }

            $seriesId = (int) ($seriesRow['id'] ?? 0);
            if ($seriesId <= 0) {
                continue;
            }

            $abmMagId = MagazineCatalogImporter::parseAbmMagazineIdFromNotes(
                (string) ($seriesRow['notes'] ?? '')
            );

            $issuesStmt = $db->prepare(
                'SELECT o.id AS oeuvre_id, o.poster_url, o.annee,
                        om.numero, om.numero_ordre, om.date_parution, om.est_hors_serie
                 FROM oeuvre_magazine om
                 INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = ?
                 WHERE om.series_id = ?
                 ORDER BY om.numero_ordre ASC, om.numero COLLATE FRENCH_NOCASE ASC'
            );
            $issuesStmt->execute([MediaDomain::MAGAZINE, $seriesId]);
            $issueRows = $issuesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $issues = [];
            foreach ($issueRows as $issueRow) {
                $dateRaw = trim((string) ($issueRow['date_parution'] ?? ''));
                $dateLabel = $dateRaw;
                if ($dateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) === 1) {
                    $dateLabel = PublicationType::formatParutionDate(
                        $dateRaw,
                        (string) ($seriesRow['publication_type'] ?? PublicationType::MENSUEL)
                    );
                }

                $issues[] = [
                    'oeuvre_id' => (int) ($issueRow['oeuvre_id'] ?? 0),
                    'numero' => (string) ($issueRow['numero'] ?? ''),
                    'numero_ordre' => (float) ($issueRow['numero_ordre'] ?? 0),
                    'hors_serie' => (int) ($issueRow['est_hors_serie'] ?? 0) === 1,
                    'date_label' => $dateLabel,
                    'date_parution' => $dateRaw,
                    'annee' => max(0, (int) ($issueRow['annee'] ?? 0)),
                    'cover_url' => MagazineCatalogImporter::normalizePosterUrl(
                        (string) ($issueRow['poster_url'] ?? '')
                    ),
                ];
                $issueCount++;
            }

            $logoUrl = MagazineCatalogImporter::normalizePosterUrl(
                (string) ($seriesRow['poster_url'] ?? '')
            );

            $exportSeries[] = [
                'abm_magazine_id' => $abmMagId,
                'titre' => $titre,
                'logo_url' => $logoUrl,
                'publication_type' => (string) ($seriesRow['publication_type'] ?? ''),
                'editeur' => (string) ($seriesRow['editeur'] ?? ''),
                'issues' => $issues,
            ];
        }

        return [
            'format_version' => 1,
            'source' => 'mediatheque-catalog-export',
            'generated_at' => gmdate('c'),
            'stats' => [
                'series_count' => count($exportSeries),
                'issue_count' => $issueCount,
            ],
            'series' => $exportSeries,
        ];
    }

    /** @param list<string> $filters */
    private function titleMatchesFilters(string $titre, array $filters): bool
    {
        $haystack = mb_strtolower($titre);
        foreach ($filters as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function normalizeTitleFilters(array $filters): array
    {
        $out = [];
        foreach ($filters as $filter) {
            $filter = trim((string) $filter);
            if ($filter !== '') {
                $out[] = $filter;
            }
        }

        return $out;
    }
}

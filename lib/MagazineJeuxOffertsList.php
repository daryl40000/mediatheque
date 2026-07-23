<?php
/**
 * Liste des numéros magazines ayant offert un jeu (catégorie « Jeux offerts »).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineJeuxOffertsList
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return MagazineRepository::isAvailable()
            && MagazineSubjectRepository::isAvailable()
            && MagazineGameLink::catalogColumnExists();
    }

    /**
     * Numéros avec jeu offert, regroupés par série, ordonnés par parution.
     *
     * @return list<array{
     *   series_id: int,
     *   series_titre: string,
     *   series_url: string,
     *   issues: list<array{
     *     issue_oeuvre_id: int,
     *     numero: string,
     *     date_parution: string,
     *     date_label: string,
     *     game_titre: string,
     *     game_url: string,
     *     issue_url: string
     *   }>
     * }>
     */
    public function listGroupedBySeries(int $userId, int $foyerId): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT s.id AS series_id,
                    s.titre AS series_titre,
                    s.publication_type,
                    om.oeuvre_id AS issue_oeuvre_id,
                    om.numero,
                    om.numero_ordre,
                    om.date_parution,
                    ms.id AS subject_id,
                    ms.label AS subject_label,
                    ms.detail AS subject_detail,
                    ms.parution_year AS subject_year,
                    ms.catalog_oeuvre_id,
                    o_game.titre AS game_titre,
                    o_game.titre_original AS game_titre_original,
                    b.id AS bib_id
             FROM magazine_subject ms
             INNER JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = oms.oeuvre_id
             INNER JOIN oeuvres o_issue
                ON o_issue.id = om.oeuvre_id
               AND o_issue.media_domain = :magazine_domain
             INNER JOIN series s
                ON s.id = om.series_id
               AND s.media_domain = :magazine_domain
             LEFT JOIN oeuvres o_game
                ON o_game.id = ms.catalog_oeuvre_id
               AND o_game.media_domain = :game_domain
             LEFT JOIN bibliotheque b ON b.oeuvre_id = om.oeuvre_id
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )
             WHERE ms.category = :category
             ORDER BY s.titre COLLATE FRENCH_NOCASE ASC,
                      om.date_parution ASC,
                      om.numero_ordre ASC,
                      ms.label COLLATE FRENCH_NOCASE ASC'
        );
        $stmt->execute([
            'magazine_domain' => MediaDomain::MAGAZINE,
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
            'category' => MagazineSubject::JEUX_OFFERTS,
        ]);

        /** @var array<int, array<string, mixed>> $grouped */
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $seriesId = (int) ($row['series_id'] ?? 0);
            if ($seriesId <= 0) {
                continue;
            }

            if (!isset($grouped[$seriesId])) {
                $grouped[$seriesId] = [
                    'series_id' => $seriesId,
                    'series_titre' => (string) ($row['series_titre'] ?? ''),
                    'series_url' => View::magazineSeriesUrl($seriesId),
                    'issues' => [],
                ];
            }

            $catalogOeuvreId = (int) ($row['catalog_oeuvre_id'] ?? 0);
            $gameTitre = '';
            if ($catalogOeuvreId > 0 && trim((string) ($row['game_titre'] ?? '')) !== '') {
                $gameTitre = GameTitle::displayTitle([
                    'titre' => (string) ($row['game_titre'] ?? ''),
                    'titre_original' => (string) ($row['game_titre_original'] ?? ''),
                ]);
            }
            if ($gameTitre === '') {
                $gameTitre = MagazineSubject::displayLabel(
                    (string) ($row['subject_label'] ?? ''),
                    (string) ($row['subject_detail'] ?? ''),
                    (int) ($row['subject_year'] ?? 0)
                );
            }
            if ($gameTitre === '') {
                $gameTitre = 'Jeu offert';
            }

            $bibId = (int) ($row['bib_id'] ?? 0);
            $issueOeuvreId = (int) ($row['issue_oeuvre_id'] ?? 0);
            $issueUrl = $bibId > 0
                ? View::magazineIssueNavUrl($bibId)
                : ($issueOeuvreId > 0 ? View::oeuvreMagazineNavUrl($issueOeuvreId) : '');

            $gameUrl = $catalogOeuvreId > 0
                ? View::gameMagazinesUrl($catalogOeuvreId, $bibId)
                : '';

            $dateParution = (string) ($row['date_parution'] ?? '');
            $grouped[$seriesId]['issues'][] = [
                'issue_oeuvre_id' => $issueOeuvreId,
                'numero' => (string) ($row['numero'] ?? ''),
                'numero_ordre' => (float) ($row['numero_ordre'] ?? 0),
                'date_parution' => $dateParution,
                'date_sort_key' => PublicationType::parseParutionDateLabel($dateParution)
                    ?? sprintf('%04d-01-01', MagazineSeriesStats::extractYear($dateParution) ?? 0),
                'date_label' => PublicationType::formatParutionDate(
                    $dateParution,
                    (string) ($row['publication_type'] ?? PublicationType::MENSUEL)
                ),
                'game_titre' => $gameTitre,
                'game_url' => $gameUrl,
                'issue_url' => $issueUrl,
                'subject_id' => (int) ($row['subject_id'] ?? 0),
            ];
        }

        foreach ($grouped as &$group) {
            usort(
                $group['issues'],
                static function (array $a, array $b): int {
                    $dateCmp = strcmp(
                        (string) ($a['date_sort_key'] ?? ''),
                        (string) ($b['date_sort_key'] ?? '')
                    );
                    if ($dateCmp !== 0) {
                        return $dateCmp;
                    }
                    $ordreCmp = ((float) ($a['numero_ordre'] ?? 0)) <=> ((float) ($b['numero_ordre'] ?? 0));
                    if ($ordreCmp !== 0) {
                        return $ordreCmp;
                    }

                    return strcmp((string) ($a['numero'] ?? ''), (string) ($b['numero'] ?? ''));
                }
            );
            foreach ($group['issues'] as &$issue) {
                unset($issue['date_sort_key'], $issue['numero_ordre']);
            }
            unset($issue);
        }
        unset($group);

        return array_values($grouped);
    }
}

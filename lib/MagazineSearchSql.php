<?php
/** Fragments SQL de recherche globale magazines. */
declare(strict_types=1);
namespace Moncine;
final class MagazineSearchSql {
    public function __construct(private readonly MagazineLibraryQuery $libraryQuery) {}
    /**
     * Recherche globale dans les numéros d’une série (n°, date, sommaire, texte PDF pages 1–6).
     * Utilise FTS5 si disponible, sinon LIKE.
     *
     * @return array{0: string, 1: array<string, int|string>}
     */
    public function issueGlobalSearchFilterSql(string $searchQuery): array
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return ['', []];
        }

        $orParts = [];
        $params = [];

        // Si l’utilisateur tape juste un numéro (ex. "20"), on veut filtrer sur le n°,
        // sans « bruit » (ex. années 2023/2024 dans l’index FTS ou la date ISO).
        if (preg_match('/^\d+$/', $searchQuery) === 1) {
            $orParts[] = 'LOWER(TRIM(om.numero)) = LOWER(:search_g_numero_exact)';
            $params['search_g_numero_exact'] = $searchQuery;

            $asNumber = (int) $searchQuery;
            if ($asNumber > 0) {
                $orParts[] = 'CAST(om.numero_ordre AS INTEGER) = :search_g_numero_ordre_int';
                $params['search_g_numero_ordre_int'] = $asNumber;
            }

            return ['(' . implode(' OR ', $orParts) . ')', $params];
        }

        $parsed = PublicationType::parseParutionDateFilter($searchQuery);
        if ($parsed !== null) {
            $orParts[] = "CAST(strftime('%Y', om.date_parution) AS INTEGER) = :search_g_year";
            $params['search_g_year'] = $parsed['year'];
            if ($parsed['month'] !== null) {
                $orParts[] = "CAST(strftime('%m', om.date_parution) AS INTEGER) = :search_g_month";
                $params['search_g_month'] = $parsed['month'];
            }
        }

        $ftsMatch = MagazineIssueFts::isAvailable()
            ? MagazineIssueFts::matchExpression($searchQuery)
            : '';
        if ($ftsMatch !== '') {
            $orParts[] = 'om.oeuvre_id IN (
                SELECT magazine_issue_fts.oeuvre_id
                FROM magazine_issue_fts
                WHERE magazine_issue_fts.series_id = om.series_id
                  AND magazine_issue_fts MATCH :search_fts
            )';
            $params['search_fts'] = $ftsMatch;
        } elseif ($parsed === null) {
            $fragment = LikePattern::containsFragment($searchQuery);
            $likeParts = [
                'LOWER(om.numero) LIKE LOWER(:search_g_numero) ESCAPE \'\\\'',
                'LOWER(COALESCE(om.sommaire, \'\')) LIKE LOWER(:search_g_sommaire) ESCAPE \'\\\'',
            ];
            $params['search_g_numero'] = $fragment;
            $params['search_g_sommaire'] = $fragment;

            if (MagazineRepository::pdfTextPreviewColumnExists()) {
                $likeParts[] = 'LOWER(COALESCE(om.pdf_text_preview, \'\')) LIKE LOWER(:search_g_pdf) ESCAPE \'\\\'';
                $params['search_g_pdf'] = $fragment;
            }

            $orParts[] = '(' . implode(' OR ', $likeParts) . ')';
        }

        if (MagazineSubjectRepository::isAvailable()) {
            [$subjectSql, $subjectParams] = $this->subjectGlobalSearchMatchSql($searchQuery);
            if ($subjectSql !== '') {
                [$issueSubjectSql, $issueSubjectParams] = $this->subjectSearchSqlForAlias(
                    $subjectSql,
                    $subjectParams,
                    'ms_issue',
                    'issue'
                );
                $orParts[] = 'om.oeuvre_id IN (
                    SELECT oms_issue.oeuvre_id
                    FROM oeuvre_magazine_subject oms_issue
                    INNER JOIN magazine_subject ms_issue ON ms_issue.id = oms_issue.subject_id
                    WHERE ' . $issueSubjectSql . '
                )';
                $params = array_merge($params, $issueSubjectParams);
            }
        }

        if ($orParts === []) {
            return ['', []];
        }

        return ['(' . implode(' OR ', $orParts) . ')', $params];
    }

    /**
     * Filtre séries : titre, contenu des numéros ou sujets associés (bibliothèque).
     *
     * @param array<string, int|string> $params
     */
    public function seriesGlobalSearchFilterSql(
        string $searchQuery,
        int $userId,
        int $foyerId,
        ?string $statut,
        array &$params
    ): string {
        $searchParts = ['LOWER(s.titre) LIKE LOWER(:series_q) ESCAPE \'\\\''];
        $params['series_q'] = LikePattern::containsFragment($searchQuery);

        [$issueSearchSql, $issueSearchParams] = $this->issueGlobalSearchFilterSql($searchQuery);
        if ($issueSearchSql !== '') {
            [$librarySql, $libraryParams] = $this->libraryQuery->libraryStatutFilter($statut, $userId, $foyerId);
            $librarySqlInSub = str_replace('b.', 'b_gs.', $librarySql);
            $params = array_merge($params, $libraryParams, $issueSearchParams);
            $params['domain_gs'] = MediaDomain::MAGAZINE;
            $searchParts[] = 's.id IN (
                SELECT DISTINCT om_gs.series_id
                FROM oeuvre_magazine om_gs
                INNER JOIN oeuvres o_gs ON o_gs.id = om_gs.oeuvre_id AND o_gs.media_domain = :domain_gs
                INNER JOIN bibliotheque b_gs ON b_gs.oeuvre_id = o_gs.id
                WHERE ' . $librarySqlInSub . ' AND ' . str_replace('om.', 'om_gs.', $issueSearchSql) . '
            )';
        }

        if (MagazineSubjectRepository::isAvailable()) {
            [$subjectSql, $subjectParams] = $this->subjectGlobalSearchMatchSql($searchQuery);
            if ($subjectSql !== '') {
                [$librarySql, $libraryParams] = $this->libraryQuery->libraryStatutFilter($statut, $userId, $foyerId);
                $librarySqlInSub = str_replace('b.', 'b_sub.', $librarySql);
                $params = array_merge($params, $libraryParams, $subjectParams);
                $params['domain_sub'] = MediaDomain::MAGAZINE;
                $searchParts[] = 's.id IN (
                    SELECT DISTINCT om_sub.series_id
                    FROM oeuvre_magazine om_sub
                    INNER JOIN oeuvres o_sub ON o_sub.id = om_sub.oeuvre_id AND o_sub.media_domain = :domain_sub
                    INNER JOIN bibliotheque b_sub ON b_sub.oeuvre_id = o_sub.id
                    INNER JOIN oeuvre_magazine_subject oms ON oms.oeuvre_id = om_sub.oeuvre_id
                    INNER JOIN magazine_subject ms ON ms.id = oms.subject_id
                    WHERE ' . $librarySqlInSub . ' AND ' . $subjectSql . '
                )';
            }
        }

        return '(' . implode(' OR ', $searchParts) . ')';
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    public function subjectGlobalSearchMatchSql(string $searchQuery): array
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return ['', []];
        }

        $matchParts = [];
        $params = [];

        $ftsMatch = MagazineSubjectFts::isAvailable()
            ? MagazineSubjectFts::matchExpression($searchQuery)
            : '';
        if ($ftsMatch !== '') {
            $matchParts[] = 'ms.id IN (
                    SELECT magazine_subject_fts.subject_id
                    FROM magazine_subject_fts
                    WHERE magazine_subject_fts MATCH :series_subj_fts
                )';
            $params['series_subj_fts'] = $ftsMatch;
        } else {
            $matchParts[] = '(LOWER(ms.label) LIKE LOWER(:series_subj_q) ESCAPE \'\\\'
                OR LOWER(ms.detail) LIKE LOWER(:series_subj_q_detail) ESCAPE \'\\\')';
            $params['series_subj_q'] = LikePattern::containsFragment($searchQuery);
            $params['series_subj_q_detail'] = LikePattern::containsFragment($searchQuery);
        }

        if (MagazineGameLink::isAvailable()) {
            $matchParts[] = '(ms.catalog_oeuvre_id IS NOT NULL AND EXISTS (
                SELECT 1
                FROM oeuvres o_subj_game
                INNER JOIN oeuvre_jeu oj_subj_game ON oj_subj_game.oeuvre_id = o_subj_game.id
                WHERE o_subj_game.id = ms.catalog_oeuvre_id
                  AND o_subj_game.media_domain = :subj_game_domain
                  AND (
                      fold_search(o_subj_game.titre) LIKE :subj_game_title ESCAPE \'\\\'
                      OR fold_search(COALESCE(oj_subj_game.alternative_names, \'\')) LIKE :subj_game_acronym ESCAPE \'\\\'
                  )
            ))';
            $gamePattern = SearchMatch::foldedContainsPattern($searchQuery);
            $params['subj_game_domain'] = MediaDomain::JEU;
            $params['subj_game_title'] = $gamePattern;
            $params['subj_game_acronym'] = $gamePattern;
        }

        return ['(' . implode(' OR ', $matchParts) . ')', $params];
    }

    /**
     * Remplace l’alias ms et préfixe les paramètres nommés pour une sous-requête.
     *
     * @param array<string, int|string> $params
     * @return array{0: string, 1: array<string, int|string>}
     */
    public function subjectSearchSqlForAlias(
        string $subjectSql,
        array $params,
        string $tableAlias,
        string $paramSuffix
    ): array {
        $sql = str_replace('ms.', $tableAlias . '.', $subjectSql);
        $outParams = [];
        foreach ($params as $key => $value) {
            $newKey = $key . '_' . $paramSuffix;
            $sql = str_replace(':' . $key, ':' . $newKey, $sql);
            $outParams[$newKey] = $value;
        }

        return [$sql, $outParams];
    }
}

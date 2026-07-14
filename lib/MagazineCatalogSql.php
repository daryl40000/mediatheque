<?php
declare(strict_types=1);
namespace Moncine;
final class MagazineCatalogSql {
    public static function selectCatalogIssueRow(): string {
        return 'o.id AS oeuvre_id, o.titre, o.poster_url, om.series_id, om.numero, om.numero_ordre, om.date_parution, om.sommaire, om.pages, om.est_hors_serie, om.stored_object_id, s.titre AS series_titre, s.publication_type, s.editeur, s.issn, s.poster_url AS series_poster_url, s.tags AS series_tags, s.categories AS series_categories';
    }
    public static function filterParamsForSql(string $sql, array $params): array {
        if (!preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $m)) return [];
        $f=[]; foreach(array_unique($m[1]) as $n) if(array_key_exists($n,$params)) $f[$n]=$params[$n]; return $f;
    }
    public static function seriesOrderClause(string $sortBy, string $sortDir): string {
        $dir=strtolower($sortDir)==='desc'?'DESC':'ASC';
        return match($sortBy){'issues'=>'issue_count '.$dir.', s.titre COLLATE FRENCH_NOCASE ASC','last_date'=>'last_date_parution '.$dir.', s.titre COLLATE FRENCH_NOCASE ASC',default=>'s.titre COLLATE FRENCH_NOCASE '.$dir};
    }
    public static function issueOrderClause(string $sortBy, string $sortDir): string {
        $dir=strtolower($sortDir)==='desc'?'DESC':'ASC';
        return match($sortBy){'numero'=>'om.numero_ordre '.$dir.', om.date_parution '.$dir,'date'=>'om.date_parution '.$dir.', om.numero_ordre '.$dir,'titre'=>'o.titre COLLATE FRENCH_NOCASE '.$dir,default=>'om.numero_ordre '.$dir.', om.date_parution '.$dir};
    }
    public static function sqlIssuePossessedCondition(string $b, string $om): string {
        $s="LOWER(COALESCE($b.support_physique, ''))";
        return "(( $om.stored_object_id IS NOT NULL AND $om.stored_object_id > 0) OR (INSTR($s, 'papier') > 0) OR (INSTR($s, 'pdf') > 0) OR (INSTR($s, 'physique') > 0) OR (INSTR($s, 'demat') > 0) OR (INSTR($s, 'démat') > 0))";
    }
}

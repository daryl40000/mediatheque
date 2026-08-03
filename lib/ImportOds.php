<?php
/**
 * Import ODS export Moncine (feuilles Films + Historique).
 */

declare(strict_types=1);

namespace Moncine;

final class ImportOds
{
    public function __construct(
        private readonly ImportRunner $runner = new ImportRunner()
    ) {
    }

    /**
     * @return array{imported: int, vues: int, errors: list<string>}
     */
    public function importFromPath(string $path, bool $replaceCatalog = false): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return [
                'imported' => 0,
                'vues' => 0,
                'errors' => ['Extension PHP ZipArchive requise pour l’import ODS. Utilisez le CSV ou installez php-zip.'],
            ];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['imported' => 0, 'vues' => 0, 'errors' => ['Impossible d’ouvrir le fichier ODS.']];
        }

        $content = $zip->getFromName('content.xml');
        $zip->close();

        if ($content === false || trim($content) === '') {
            return ['imported' => 0, 'vues' => 0, 'errors' => ['Fichier ODS invalide (content.xml manquant).']];
        }

        $tables = $this->parseOdsTables($content);
        if ($tables === []) {
            return ['imported' => 0, 'vues' => 0, 'errors' => ['Aucune feuille trouvée dans le fichier ODS.']];
        }

        $result = ['imported' => 0, 'vues' => 0, 'errors' => []];

        // Séries magazines d’abord : les numéros du catalogue y font référence.
        $seriesMagTable = $this->findTable($tables, [
            'seriesmagazines',
            'series magazines',
            'series_magazines',
            'magazines series',
        ]);
        if ($seriesMagTable !== null) {
            [$header, $rows] = $seriesMagTable;
            $seriesResult = CatalogMagazineSheets::importSeriesSheet($rows, $header);
            $result['errors'] = array_merge($result['errors'], $seriesResult['errors']);
        }

        $catalogTable = $this->findTable($tables, ['catalogue', 'catalog']);
        if ($catalogTable !== null) {
            [$header, $rows] = $catalogTable;
            $catalogResult = $this->runner->importCatalogSheet($rows, $header, $replaceCatalog);
            $result = ImportRunner::mergeResults($result, $catalogResult);
        }

        $filmsTable = $this->findTable($tables, ['bibliotheque', 'films', 'film']);
        if ($filmsTable !== null) {
            [$header, $rows] = $filmsTable;
            $filmResult = $this->runner->importFilmsSheet($rows, $header, $replaceCatalog);
            $result = ImportRunner::mergeResults($result, $filmResult);
        } elseif ($catalogTable === null) {
            $result['errors'][] = 'Feuille « Bibliotheque » ou « Catalogue » introuvable.';
        }

        $histTable = $this->findTable($tables, ['historique', 'history']);
        if ($histTable !== null) {
            [$header, $rows] = $histTable;
            $histResult = $this->runner->importHistoriqueSheet($rows, $header);
            $result['vues'] += $histResult['vues'];
            $result['errors'] = array_merge($result['errors'], $histResult['errors']);
        }

        // Sujets + liens après les œuvres (IDs catalogue déjà présents).
        $subjectsTable = $this->findTable($tables, [
            'magazinesubjects',
            'magazine subjects',
            'magazine_subjects',
            'sujets magazines',
        ]);
        if ($subjectsTable !== null) {
            [$header, $rows] = $subjectsTable;
            $subjectsResult = CatalogMagazineSheets::importSubjectsSheet($rows, $header);
            $result['errors'] = array_merge($result['errors'], $subjectsResult['errors']);
        }

        $subjectLinksTable = $this->findTable($tables, [
            'magazinesubjectlinks',
            'magazine subject links',
            'magazine_subject_links',
            'liens sujets magazines',
        ]);
        if ($subjectLinksTable !== null) {
            [$header, $rows] = $subjectLinksTable;
            $linksResult = CatalogMagazineSheets::importSubjectLinksSheet($rows, $header);
            $result['errors'] = array_merge($result['errors'], $linksResult['errors']);
        }

        $supplementsTable = $this->findTable($tables, [
            'magazinesupplements',
            'magazine supplements',
            'magazine_supplements',
            'supplements magazines',
        ]);
        if ($supplementsTable !== null) {
            [$header, $rows] = $supplementsTable;
            $suppResult = CatalogMagazineSheets::importSupplementsSheet($rows, $header);
            $result['errors'] = array_merge($result['errors'], $suppResult['errors']);
        }

        $suppLinksTable = $this->findTable($tables, [
            'magazinesupplementlinks',
            'magazine supplement links',
            'magazine_supplement_links',
            'liens supplements magazines',
        ]);
        if ($suppLinksTable !== null) {
            [$header, $rows] = $suppLinksTable;
            $suppLinksResult = CatalogMagazineSheets::importSupplementLinksSheet($rows, $header);
            $result['errors'] = array_merge($result['errors'], $suppLinksResult['errors']);
        }

        return $result;
    }

    /**
     * @return array<string, array{0: list<string|null>, 1: list<list<string|null>>}>
     */
    private function parseOdsTables(string $xml): array
    {
        $dom = new \DOMDocument();
        if (@$dom->loadXML($xml) === false) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

        $out = [];
        $tableNodes = $xpath->query('//table:table');
        if ($tableNodes === false) {
            return [];
        }

        foreach ($tableNodes as $tableNode) {
            if (!$tableNode instanceof \DOMElement) {
                continue;
            }
            $name = trim($tableNode->getAttribute('table:name'));
            if ($name === '') {
                $name = 'Feuille' . (count($out) + 1);
            }

            $rows = [];
            $rowNodes = $xpath->query('table:table-row', $tableNode);
            if ($rowNodes === false) {
                continue;
            }

            foreach ($rowNodes as $rowNode) {
                if (!$rowNode instanceof \DOMElement) {
                    continue;
                }
                $cells = [];
                $cellNodes = $xpath->query('table:table-cell', $rowNode);
                if ($cellNodes === false) {
                    continue;
                }
                foreach ($cellNodes as $cellNode) {
                    if (!$cellNode instanceof \DOMElement) {
                        continue;
                    }
                    $texts = [];
                    $pNodes = $xpath->query('.//text:p', $cellNode);
                    if ($pNodes !== false) {
                        foreach ($pNodes as $p) {
                            if (!$p instanceof \DOMElement) {
                                continue;
                            }
                            $texts[] = $p->textContent;
                        }
                    }
                    $cells[] = trim(implode("\n", $texts));
                }
                $rows[] = $cells;
            }

            if ($rows !== []) {
                $header = array_shift($rows);
                $out[ImportFilmRows::normalizeHeader($name)] = [$header, $rows];
            }
        }

        return $out;
    }

    /**
     * @param array<string, array{0: list<string|null>, 1: list<list<string|null>>}> $tables
     * @param list<string> $aliases
     * @return array{0: list<string|null>, 1: list<list<string|null>>}|null
     */
    private function findTable(array $tables, array $aliases): ?array
    {
        foreach ($tables as $name => $data) {
            foreach ($aliases as $alias) {
                if ($name === $alias || str_contains($name, $alias)) {
                    return $data;
                }
            }
        }

        return null;
    }
}

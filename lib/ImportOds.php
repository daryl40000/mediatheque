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

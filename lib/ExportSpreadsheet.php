<?php
/**
 * Utilitaires communs export CSV / ODS / ZIP.
 */

declare(strict_types=1);

namespace Moncine;

final class ExportSpreadsheet
{
    /**
     * @param list<list<string>> $rows
     */
    public static function sendCsv(array $rows, string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            throw new \RuntimeException('Impossible d\'écrire le fichier CSV.');
        }

        foreach ($rows as $row) {
            fputcsv($out, $row, MONCINE_CSV_DELIMITER);
        }

        fclose($out);
    }

    /**
     * @param array<string, list<list<string>>> $sheets nom => lignes
     */
    public static function sendOds(array $sheets, string $filename): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException(
                'L’extension PHP ZipArchive est requise pour l’export ODS. Utilisez le CSV ou installez php-zip.'
            );
        }

        $tmp = tempnam(sys_get_temp_dir(), 'moncine_ods_');
        if ($tmp === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire.');
        }

        $zipPath = $tmp . '.ods';
        rename($tmp, $zipPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier ODS.');
        }

        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
        $zip->setCompressionName('mimetype', \ZipArchive::CM_STORE);
        $zip->addFromString('META-INF/manifest.xml', self::odsManifestXml());
        $zip->addFromString('content.xml', self::odsContentXml($sheets));
        $zip->close();

        header('Content-Type: application/vnd.oasis.opendocument.spreadsheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($zipPath));
        header('Cache-Control: no-store');

        readfile($zipPath);
        @unlink($zipPath);
    }

    public static function buildFilename(string $prefix, string $ext): string
    {
        return $prefix . '-' . date('Y-m-d') . '.' . $ext;
    }

    /** @param array<string, list<list<string>>> $sheets */
    private static function odsContentXml(array $sheets): string
    {
        $ns = 'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
            . 'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" '
            . 'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"';

        $body = '';
        foreach ($sheets as $name => $rows) {
            $safeName = preg_replace('/[^\w\s-]/u', '', $name) ?: 'Feuille';
            $body .= '<table:table table:name="' . self::xmlEscape($safeName) . '">';
            foreach ($rows as $row) {
                $body .= '<table:table-row>';
                foreach ($row as $cell) {
                    $body .= '<table:table-cell office:value-type="string">'
                        . '<text:p>' . self::xmlEscape((string) $cell) . '</text:p>'
                        . '</table:table-cell>';
                }
                $body .= '</table:table-row>';
            }
            $body .= '</table:table>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-content ' . $ns . '>'
            . '<office:body><office:spreadsheet>' . $body . '</office:spreadsheet></office:body>'
            . '</office:document-content>';
    }

    private static function odsManifestXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">'
            . '<manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.spreadsheet" manifest:full-path="/"/>'
            . '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>'
            . '</manifest:manifest>';
    }

    private static function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

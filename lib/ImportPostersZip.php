<?php
/**
 * Import d’une archive ZIP d’affiches (format export Moncine : posters/{id}.ext).
 */

declare(strict_types=1);

namespace Moncine;

final class ImportPostersZip
{
    /** @var array<string, string> */
    private const EXTENSIONS = [
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'webp' => 'webp',
    ];

    public function __construct(
        private readonly PosterStorage $posters = new PosterStorage(),
        private readonly OeuvreRepository $oeuvres = new OeuvreRepository()
    ) {
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importFromPath(string $zipPath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Extension PHP ZipArchive requise (paquet php-zip).'],
            ];
        }

        if (!is_file($zipPath)) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Archive ZIP introuvable.'],
            ];
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Impossible d’ouvrir l’archive ZIP.'],
            ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $entryName = (string) ($stat['name'] ?? '');
            if ($entryName === '' || str_ends_with($entryName, '/')) {
                continue;
            }

            $parsed = self::parsePosterEntryName($entryName);
            if ($parsed === null) {
                continue;
            }

            [$oeuvreId, $ext] = $parsed;
            $oeuvre = $this->oeuvres->findById($oeuvreId);
            if ($oeuvre === null) {
                $skipped++;
                $errors[] = 'ID catalogue ' . $oeuvreId . ' introuvable (« ' . basename($entryName) . ' » ignoré).';
                continue;
            }

            $binary = $zip->getFromIndex($i);
            if ($binary === false || $binary === '') {
                $skipped++;
                $errors[] = 'Lecture impossible : ' . basename($entryName);
                continue;
            }

            $webPath = $this->posters->importBinaryForOeuvre($oeuvreId, $binary);
            if ($webPath === '') {
                $skipped++;
                $errors[] = 'Image invalide ou trop lourde : ' . basename($entryName);
                continue;
            }

            $this->oeuvres->update($oeuvreId, ['poster_url' => $webPath], ['poster_url']);
            $imported++;
        }

        $zip->close();

        if ($imported === 0 && $skipped === 0 && $errors === []) {
            $errors[] = 'Aucune affiche reconnue dans le ZIP (attendu : posters/123.jpg ou 123.jpg à la racine).';
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 30),
        ];
    }

    /**
     * @return array{0: int, 1: string}|null [oeuvre_id, extension normalisée]
     */
    public static function parsePosterEntryName(string $entryName): ?array
    {
        $entryName = str_replace('\\', '/', $entryName);
        $entryName = ltrim($entryName, './');
        $base = basename($entryName);

        if (!preg_match('/^(\d+)\.([a-z0-9]+)$/i', $base, $m)) {
            return null;
        }

        $oeuvreId = (int) $m[1];
        if ($oeuvreId <= 0) {
            return null;
        }

        $ext = strtolower($m[2]);
        if (!isset(self::EXTENSIONS[$ext])) {
            return null;
        }

        // Sécurité Zip Slip : n’accepter que posters/… ou fichier à la racine de l’archive.
        $normalized = trim($entryName, '/');
        if (!preg_match('#^(?:posters/)?\d+\.(jpe?g|png|webp)$#i', $normalized)) {
            return null;
        }

        return [$oeuvreId, self::EXTENSIONS[$ext]];
    }
}

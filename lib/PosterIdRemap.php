<?php
/**
 * Recale les fichiers posters/{ancien_id}.ext vers les ID catalogue actuels.
 *
 * Utile après une migration si le catalogue a été importé sans conserver les ID,
 * mais que les affiches portent encore les anciens numéros.
 */

declare(strict_types=1);

namespace Moncine;

final class PosterIdRemap
{
    public function __construct(
        private readonly OeuvreRepository $oeuvres = new OeuvreRepository(),
        private readonly PosterStorage $posters = new PosterStorage()
    ) {
    }

    /**
     * Lit un export catalogue (CSV) contenant ID catalogue + titre + réalisateur.
     * Pour chaque ligne : retrouve l’œuvre actuelle par titre/réalisateur,
     * renomme posters/{ancien_id}.* → posters/{nouvel_id}.* et met à jour poster_url.
     *
     * @return array{remapped: int, skipped: int, errors: list<string>}
     */
    public function remapFromCatalogExportPath(string $csvPath, string $delimiter = MONCINE_CSV_DELIMITER): array
    {
        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            return ['remapped' => 0, 'skipped' => 0, 'errors' => ['Impossible d’ouvrir le fichier CSV.']];
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);

            return ['remapped' => 0, 'skipped' => 0, 'errors' => ['Fichier CSV vide.']];
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? $header[0];
        $map = ImportFilmRows::mapHeaders($header, CatalogExportSchema::COLUMN_ALIASES);

        if (!isset($map['titre'])) {
            fclose($handle);

            return ['remapped' => 0, 'skipped' => 0, 'errors' => ['Colonne « Titre » requise dans le CSV.']];
        }

        if (!isset($map['oeuvre_id'])) {
            fclose($handle);

            return [
                'remapped' => 0,
                'skipped' => 0,
                'errors' => ['Colonne « ID catalogue » requise (export catalogue de l’ancienne instance).'],
            ];
        }

        $remapped = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }

            $oldId = $this->intCell($row, $map, 'oeuvre_id');
            $titre = ImportFilmRows::getCell($row, $map, 'titre');
            $realisateur = ImportFilmRows::getCell($row, $map, 'realisateur');

            if ($oldId <= 0 || $titre === '') {
                $skipped++;
                continue;
            }

            $oeuvre = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
            if ($oeuvre === null) {
                $errors[] = 'Ligne ' . $line . ' : « ' . $titre . ' » introuvable dans le catalogue actuel.';
                $skipped++;
                continue;
            }

            $newId = (int) $oeuvre['id'];
            if ($this->remapFile($oldId, $newId)) {
                $remapped++;
            } else {
                $skipped++;
            }
        }

        fclose($handle);

        return ['remapped' => $remapped, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 40)];
    }

    private function remapFile(int $oldId, int $newId): bool
    {
        if ($oldId <= 0 || $newId <= 0) {
            return false;
        }

        $binary = $this->readPosterBinary($oldId);
        if ($binary === null) {
            return false;
        }

        $webPath = $this->posters->importBinaryForOeuvre($newId, $binary);
        if ($webPath === '') {
            return false;
        }

        $this->oeuvres->update($newId, ['poster_url' => $webPath], ['poster_url']);

        if ($oldId !== $newId) {
            $this->posters->deleteLocalForOeuvre($oldId);
        }

        return true;
    }

    private function readPosterBinary(int $oeuvreId): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = PosterStorage::postersFilesystemDir() . '/' . $oeuvreId . '.' . $ext;
            if (!is_file($path)) {
                continue;
            }

            $binary = file_get_contents($path);
            if ($binary !== false && $binary !== '') {
                return $binary;
            }
        }

        return null;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     */
    private function intCell(array $row, array $map, string $key): int
    {
        if (!isset($map[$key])) {
            return 0;
        }

        $raw = trim((string) ($row[$map[$key]] ?? ''));
        if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
            return 0;
        }

        return max(0, (int) $raw);
    }
}

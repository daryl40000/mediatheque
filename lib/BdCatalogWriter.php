<?php
/**
 * Écriture des lignes catalogue BD (table oeuvre_bd).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdCatalogWriter
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCatalogBdRow(int $oeuvreId, array $data, int $seriesId, string $kind): void
    {
        $catalogFields = $this->prepareCatalogTomeFields($data, $seriesId, null);

        $this->db->prepare(
            'INSERT INTO oeuvre_bd (
                oeuvre_id, series_id, kind, tome_numero, tome_ordre, tome_label, est_hors_serie,
                scenariste, dessinateur, editeur, genre
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $oeuvreId,
            $seriesId > 0 ? $seriesId : null,
            $kind,
            $catalogFields['tome_numero'],
            $catalogFields['tome_ordre'],
            $catalogFields['tome_label'],
            $catalogFields['est_hors_serie'],
            $catalogFields['scenariste'],
            $catalogFields['dessinateur'],
            $catalogFields['editeur'],
            $catalogFields['genre'],
        ]);
    }

    /**
     * @param array<string, mixed>|null $existing
     *
     * @return array{
     *     tome_numero: int,
     *     tome_ordre: float,
     *     tome_label: string,
     *     est_hors_serie: int,
     *     scenariste: string,
     *     dessinateur: string,
     *     editeur: string,
     *     genre: string
     * }
     */
    public function prepareCatalogTomeFields(array $data, int $seriesId, ?array $existing): array
    {
        $horsSerie = array_key_exists('est_hors_serie', $data)
            ? !empty($data['est_hors_serie'])
            : !empty($existing['est_hors_serie']);

        $ordreData = [
            'tome_ordre' => $data['tome_ordre'] ?? ($existing['tome_ordre'] ?? 0),
            'tome_numero' => $data['tome_numero'] ?? ($existing['tome_numero'] ?? 0),
            'est_hors_serie' => $horsSerie,
        ];

        return [
            'tome_numero' => max(0, (int) ($data['tome_numero'] ?? $existing['tome_numero'] ?? 0)),
            'tome_ordre' => BdTomeOrdre::resolve($ordreData, $seriesId),
            'tome_label' => trim((string) ($data['tome_label'] ?? $existing['tome_label'] ?? '')),
            'est_hors_serie' => $horsSerie ? 1 : 0,
            'scenariste' => trim((string) ($data['scenariste'] ?? $existing['scenariste'] ?? '')),
            'dessinateur' => trim((string) ($data['dessinateur'] ?? $existing['dessinateur'] ?? '')),
            'editeur' => trim((string) ($data['editeur'] ?? $existing['editeur'] ?? '')),
            'genre' => trim((string) ($data['genre'] ?? $existing['genre'] ?? '')),
        ];
    }
}

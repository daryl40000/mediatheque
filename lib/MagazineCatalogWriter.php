<?php
/**
 * Préparation des champs catalogue numéros magazines (oeuvre_magazine).
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineCatalogWriter
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     *
     * @return array{
     *     numero: string,
     *     numero_ordre: float,
     *     date_parution: ?string,
     *     sommaire: string,
     *     pages: int,
     *     est_hors_serie: int,
     *     hors_serie: bool
     * }
     */
    public function prepareIssueFields(array $data, ?array $existing, int $seriesId): array
    {
        $numero = trim((string) ($data['numero'] ?? $existing['numero'] ?? ''));
        $horsSerie = array_key_exists('est_hors_serie', $data)
            ? !empty($data['est_hors_serie'])
            : !empty($existing['est_hors_serie']);

        $numeroOrdre = (float) ($data['numero_ordre'] ?? $existing['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = is_numeric($numero) ? (float) $numero : 0.0;
        }

        if ($horsSerie && $numeroOrdre > 0 && $numeroOrdre === (float) (int) $numeroOrdre) {
            $numeroOrdre += 0.5;
        }

        $dateParution = trim((string) ($data['date_parution'] ?? $existing['date_parution'] ?? ''));

        return [
            'numero' => $numero,
            'numero_ordre' => $numeroOrdre,
            'date_parution' => $dateParution !== '' ? $dateParution : null,
            'sommaire' => trim((string) ($data['sommaire'] ?? $existing['sommaire'] ?? '')),
            'pages' => max(0, (int) ($data['pages'] ?? $existing['pages'] ?? 0)),
            'est_hors_serie' => $horsSerie ? 1 : 0,
            'hors_serie' => $horsSerie,
        ];
    }
}

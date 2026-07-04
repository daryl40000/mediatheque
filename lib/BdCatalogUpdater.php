<?php
/**
 * Mise à jour des fiches catalogue BD (œuvre + oeuvre_bd).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdCatalogUpdater
{
    public function __construct(
        private readonly PDO $db,
        private readonly BdLibraryQuery $libraryQuery,
        private readonly BdCatalogWriter $catalogWriter
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateByOeuvreId(int $oeuvreId, array $data, ?int $bibId = null): bool|string
    {
        if (!BdRepository::isAvailable() || $oeuvreId <= 0) {
            return 'Module BD non disponible.';
        }

        $resolved = BdCatalogCreator::resolveTitleAndSeries($data);
        if (is_string($resolved)) {
            return $resolved;
        }
        [$titre, $seriesId] = $resolved;

        $kind = BdKind::normalize((string) ($data['kind'] ?? BdKind::BD));
        $support = BdPhysicalSupport::normalize((string) ($data['support_physique'] ?? ''));

        $oeuvreUpdate = [
            'titre' => $titre,
            'annee' => max(0, (int) ($data['annee'] ?? 0)),
            'synopsis' => trim((string) ($data['synopsis'] ?? '')),
        ];
        $oeuvreFields = ['titre', 'annee', 'synopsis'];
        if (array_key_exists('poster_url', $data)) {
            $oeuvreUpdate['poster_url'] = trim((string) $data['poster_url']);
            $oeuvreFields[] = 'poster_url';
        }

        $existing = $this->libraryQuery->findCatalogByOeuvreId($oeuvreId);
        $catalogFields = $this->catalogWriter->prepareCatalogTomeFields($data, $seriesId, $existing);

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, $oeuvreUpdate, $oeuvreFields);

            $this->db->prepare(
                'UPDATE oeuvre_bd SET
                    series_id = ?,
                    kind = ?,
                    tome_numero = ?,
                    tome_ordre = ?,
                    tome_label = ?,
                    est_hors_serie = ?,
                    scenariste = ?,
                    dessinateur = ?,
                    editeur = ?,
                    genre = ?
                 WHERE oeuvre_id = ?'
            )->execute([
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
                $oeuvreId,
            ]);

            if ($bibId !== null && $bibId > 0) {
                $this->db->prepare('UPDATE bibliotheque SET support_physique = ? WHERE id = ?')
                    ->execute([$support, $bibId]);
            }

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de la mise à jour de l’album.';
        }
    }
}

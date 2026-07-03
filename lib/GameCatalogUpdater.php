<?php
/**
 * Mise à jour des fiches catalogue jeux (œuvre + oeuvre_jeu).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameCatalogUpdater
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateByOeuvreId(int $oeuvreId, array $data, bool $includePosterUrl = false): bool|string
    {
        if (!GameRepository::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        $titreOriginal = trim((string) ($data['titre_original'] ?? ''));
        if (GameTitle::displayTitle(['titre' => $titre, 'titre_original' => $titreOriginal]) === '') {
            return 'Le titre est obligatoire.';
        }

        $platformFields = GameFormPayload::resolveCatalogPlatformFields($data);
        $platform = $platformFields['platform'];
        $platformsCsv = $platformFields['platforms'];
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');
        $isDigital = !empty($data['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);
        $relationError = GameRelations::validateFlags($data, $oeuvreId);
        if ($relationError !== null) {
            return $relationError;
        }

        $oeuvreFields = [
            'titre' => $titre,
            'titre_original' => $titreOriginal,
            'annee' => max(0, (int) ($data['annee'] ?? 0)),
            'synopsis' => trim((string) ($data['synopsis'] ?? '')),
        ];
        $oeuvreColumns = ['titre', 'titre_original', 'annee', 'synopsis'];
        if ($includePosterUrl) {
            $oeuvreFields['poster_url'] = SecureUrl::sanitizePosterUrl(trim((string) ($data['poster_url'] ?? '')));
            $oeuvreColumns[] = 'poster_url';
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, $oeuvreFields, $oeuvreColumns);
            $this->persistOeuvreJeuRow(
                $oeuvreId,
                $data,
                $platform,
                $platformsCsv,
                $physicalSupports,
                $digitalStores,
                $isDigital
            );
            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de la mise à jour du jeu.';
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistOeuvreJeuRow(
        int $oeuvreId,
        array $data,
        string $platform,
        string $platformsCsv,
        string $physicalSupports,
        string $digitalStores,
        bool $isDigital
    ): void {
        $baseParams = [
            trim((string) ($data['studio'] ?? '')),
            trim((string) ($data['editeur'] ?? '')),
            GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
            $platform,
            $isDigital ? 1 : 0,
        ];

        if (GameSchema::hasEditionColumns()) {
            $platformsSql = GameSchema::hasPlatformsColumn() ? ', platforms = ?' : '';
            $this->db->prepare(
                'UPDATE oeuvre_jeu SET
                    studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?,
                    physical_supports = ?, digital_stores = ?'
                    . $platformsSql
                    . GameCatalogSql::igdbMetadataUpdateSet()
                    . GameRelations::updateSet()
                    . ' WHERE oeuvre_id = ?'
            )->execute(array_merge(
                $baseParams,
                [$physicalSupports, $digitalStores],
                GameSchema::hasPlatformsColumn() ? [$platformsCsv] : [],
                GameCatalogSql::igdbMetadataWriteParams($data),
                GameRelations::writeParams($data),
                [$oeuvreId]
            ));

            return;
        }

        $platformsSql = GameSchema::hasPlatformsColumn() ? ', platforms = ?' : '';
        $this->db->prepare(
            'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?'
            . $platformsSql
            . GameCatalogSql::igdbMetadataUpdateSet()
            . GameRelations::updateSet()
            . ' WHERE oeuvre_id = ?'
        )->execute(array_merge(
            $baseParams,
            GameSchema::hasPlatformsColumn() ? [$platformsCsv] : [],
            GameCatalogSql::igdbMetadataWriteParams($data),
            GameRelations::writeParams($data),
            [$oeuvreId]
        ));
    }
}

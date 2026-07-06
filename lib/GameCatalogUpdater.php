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
        $existingEdition = $this->loadEditionFieldsIfNeeded($oeuvreId, $data);
        $physicalSupports = array_key_exists('physical_supports', $data)
            ? (string) $data['physical_supports']
            : (string) ($existingEdition['physical_supports'] ?? '');
        $digitalStores = array_key_exists('digital_stores', $data)
            ? (string) $data['digital_stores']
            : (string) ($existingEdition['digital_stores'] ?? '');
        if (array_key_exists('is_digital', $data)) {
            $isDigital = !empty($data['is_digital'])
                || GameDigitalStore::hasDigitalEdition($digitalStores, false);
        } else {
            $isDigital = !empty($existingEdition['is_digital'])
                || GameDigitalStore::hasDigitalEdition($digitalStores, false);
        }
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

    /**
     * @param array<string, mixed> $data
     * @return array{physical_supports: string, digital_stores: string, is_digital: bool}|null
     */
    private function loadEditionFieldsIfNeeded(int $oeuvreId, array $data): ?array
    {
        if (!GameSchema::hasEditionColumns()) {
            return null;
        }
        if (
            array_key_exists('physical_supports', $data)
            || array_key_exists('digital_stores', $data)
            || array_key_exists('is_digital', $data)
        ) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT physical_supports, digital_stores, is_digital FROM oeuvre_jeu WHERE oeuvre_id = ?'
        );
        $stmt->execute([$oeuvreId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'physical_supports' => (string) ($row['physical_supports'] ?? ''),
            'digital_stores' => (string) ($row['digital_stores'] ?? ''),
            'is_digital' => !empty($row['is_digital']),
        ];
    }
}

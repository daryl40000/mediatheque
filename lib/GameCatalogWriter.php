<?php
/**
 * Écriture des lignes catalogue jeux (table oeuvre_jeu).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameCatalogWriter
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCatalogGameRow(
        int $oeuvreId,
        array $data,
        string $platform,
        string $platformsCsv,
        bool $isDigital,
        bool $isExtension,
        int $baseGameOeuvreId
    ): void {
        $data = array_merge($data, [
            'is_extension' => $isExtension,
            'base_game_oeuvre_id' => $baseGameOeuvreId,
        ]);
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');

        if (GameSchema::hasEditionColumns()) {
            $platformsInsert = GameSchema::hasPlatformsColumn() ? ', platforms' : '';
            $platformsValue = GameSchema::hasPlatformsColumn() ? ', ?' : '';
            $this->db->prepare(
                'INSERT INTO oeuvre_jeu (
                    oeuvre_id, studio, editeur, genre, platform, is_digital,
                    physical_supports, digital_stores'
                    . $platformsInsert
                    . GameRelations::insertColumns()
                    . '
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?'
                    . $platformsValue
                    . GameRelations::insertPlaceholders()
                    . ')'
            )->execute(array_merge([
                $oeuvreId,
                trim((string) ($data['studio'] ?? '')),
                trim((string) ($data['editeur'] ?? '')),
                GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
                $platform,
                $isDigital ? 1 : 0,
                $physicalSupports,
                $digitalStores,
            ], GameSchema::hasPlatformsColumn() ? [$platformsCsv] : [], GameRelations::writeParams($data)));

            return;
        }

        $platformsInsert = GameSchema::hasPlatformsColumn() ? ', platforms' : '';
        $platformsValue = GameSchema::hasPlatformsColumn() ? ', ?' : '';
        $this->db->prepare(
            'INSERT INTO oeuvre_jeu (
                oeuvre_id, studio, editeur, genre, platform, is_digital'
                . $platformsInsert
                . GameRelations::insertColumns()
                . '
             ) VALUES (?, ?, ?, ?, ?, ?'
                . $platformsValue
                . GameRelations::insertPlaceholders()
                . ')'
        )->execute(array_merge([
            $oeuvreId,
            trim((string) ($data['studio'] ?? '')),
            trim((string) ($data['editeur'] ?? '')),
            GameGenre::normalizeInput((string) ($data['genre'] ?? '')),
            $platform,
            $isDigital ? 1 : 0,
        ], GameSchema::hasPlatformsColumn() ? [$platformsCsv] : [], GameRelations::writeParams($data)));
    }
}

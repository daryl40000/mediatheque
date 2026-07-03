<?php
/**
 * Complète une entrée bibliothèque jeu après rattachement au catalogue.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameLibraryAttach
{
    public function __construct(
        private readonly PDO $db,
        private readonly GameLibraryQuery $libraryQuery
    ) {
    }

    /**
     * @param array<string, mixed> $details
     */
    public function applyDetailsAfterCatalogAttach(int $bibId, int $oeuvreId, array $details): void
    {
        if ($bibId <= 0 || $details === []) {
            return;
        }

        $game = $this->libraryQuery->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return;
        }

        $catalogCsv = GamePlatformList::serializeList(GamePlatformList::catalogKeysFromRow($game));
        $ownedFields = GameFormPayload::ownedPlatformsFromPost($details, $catalogCsv);
        if ($ownedFields['owned_platforms'] !== '' || array_key_exists('owned_platforms', $details)) {
            GameLibraryFields::saveOwnedPlatforms($this->db, $bibId, $ownedFields['owned_platforms']);
        }

        $ownedList = GamePlatformList::parseList($ownedFields['owned_platforms']);
        if ($ownedList === []) {
            $ownedList = GamePlatformList::catalogKeysFromRow($game);
        }
        $linuxPlatform = in_array(GamePlatform::PC, $ownedList, true)
            ? GamePlatform::PC
            : GamePlatform::normalize((string) ($details['platform'] ?? $game['platform'] ?? ''));
        if (array_key_exists('tested_on_linux', $details) || array_key_exists('linux_not_supported', $details)) {
            GameLibraryFields::saveLinuxFlags(
                $this->db,
                $bibId,
                $linuxPlatform,
                !empty($details['tested_on_linux']),
                !empty($details['linux_not_supported'])
            );
        }

        if (array_key_exists('non_pretable', $details)) {
            GameLibraryFields::saveNonPretable($this->db, $bibId, !empty($details['non_pretable']));
        }

        if (!GameSchema::hasEditionColumns()) {
            return;
        }

        $physicalSupports = (string) ($details['physical_supports'] ?? '');
        $digitalStores = (string) ($details['digital_stores'] ?? '');
        $isDigital = !empty($details['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, false);

        if ($physicalSupports === '' && $digitalStores === '' && !$isDigital) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_jeu SET physical_supports = ?, digital_stores = ?, is_digital = ? WHERE oeuvre_id = ?'
        )->execute([
            $physicalSupports,
            $digitalStores,
            $isDigital ? 1 : 0,
            $oeuvreId,
        ]);
    }
}

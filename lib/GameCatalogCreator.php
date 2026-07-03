<?php
/**
 * Création de fiches catalogue jeux (avec ou sans entrée bibliothèque).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameCatalogCreator
{
    public function __construct(
        private readonly PDO $db,
        private readonly GameCatalogWriter $catalogWriter
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string ID œuvre ou message d’erreur
     */
    public function createCatalogOnly(array $data): int|string
    {
        if (!GameRepository::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        if (max(0, (int) ($data['oeuvre_id'] ?? 0)) > 0) {
            return 'Ce jeu est déjà au catalogue. Utilisez la liste ci-dessous pour le consulter.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $existing = (new OeuvreRepository())->findByTitreRealisateurAndDomain(
            $titre,
            '',
            MediaDomain::JEU
        );
        if ($existing !== null) {
            return 'Un jeu avec ce titre existe déjà au catalogue.';
        }

        $platformFields = GameFormPayload::resolveCatalogPlatformFields($data);
        $platform = $platformFields['platform'];
        $relationError = GameRelations::validateFlags($data);
        if ($relationError !== null) {
            return $relationError;
        }
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'titre_original' => trim((string) ($data['titre_original'] ?? '')),
                'realisateur' => '',
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::JEU,
            ]);

            $this->catalogWriter->insertCatalogGameRow(
                $oeuvreId,
                $data,
                $platform,
                $platformFields['platforms'],
                false,
                $isExtension,
                $baseGameOeuvreId
            );

            $this->db->commit();

            return $oeuvreId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de l’enregistrement du jeu au catalogue.';
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createWithLibrary(
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        if (!GameRepository::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $statut = LibraryStatut::normalize($statut);
        $platformFields = GameFormPayload::resolveCatalogPlatformFields($data);
        $platform = $platformFields['platform'];
        $ownedFields = GameFormPayload::ownedPlatformsFromPost($data, $platformFields['platforms']);
        if ($ownedFields['owned_platforms'] === '' && $platformFields['platforms'] !== '') {
            $ownedFields = [
                'owned_platforms' => $platformFields['platforms'],
                'owned_platform_list' => $platformFields['platform_list'],
            ];
        }
        $physicalSupports = (string) ($data['physical_supports'] ?? '');
        $digitalStores = (string) ($data['digital_stores'] ?? '');
        $isDigital = !empty($data['is_digital'])
            || GameDigitalStore::hasDigitalEdition($digitalStores, !empty($data['is_digital']));
        $relationError = GameRelations::validateFlags($data);
        if ($relationError !== null) {
            return $relationError;
        }
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::JEU,
            ]);

            $this->catalogWriter->insertCatalogGameRow(
                $oeuvreId,
                $data,
                $platform,
                $platformFields['platforms'],
                $isDigital,
                $isExtension,
                $baseGameOeuvreId
            );

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => trim((string) ($data['support_physique'] ?? '')),
            ]);

            $linuxPlatform = in_array(GamePlatform::PC, $ownedFields['owned_platform_list'], true)
                ? GamePlatform::PC
                : $platform;
            GameLibraryFields::saveLinuxFlags(
                $this->db,
                $bibId,
                $linuxPlatform,
                !empty($data['tested_on_linux']),
                !empty($data['linux_not_supported'])
            );
            GameLibraryFields::saveNonPretable($this->db, $bibId, !empty($data['non_pretable']));
            GameLibraryFields::saveOwnedPlatforms($this->db, $bibId, $ownedFields['owned_platforms']);

            $this->db->commit();

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de l’enregistrement du jeu.';
        }
    }
}

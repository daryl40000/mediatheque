<?php
/**
 * Création d’un film dans la bibliothèque (à partir du catalogue ou d’un formulaire complet).
 */

declare(strict_types=1);

namespace Moncine;

final class FilmCatalogCreator
{
    public function __construct(
        private readonly OeuvreRepository $oeuvres,
        private readonly FilmCatalogImport $import,
        private readonly FilmLibraryAttach $attach,
        private readonly FilmCatalogUpdater $updater,
        private readonly FilmLibraryQuery $libraryQuery
    ) {
    }

    /**
     * Crée un film dans la bibliothèque (collection ou wishlist).
     *
     * @param array<string, mixed> $data Champs formulaire (comme updateManual)
     * @return int|string ID bibliothèque si OK, sinon message d’erreur
     */
    public function createManual(array $data, string $statut): int|string
    {
        $statut = LibraryStatut::normalize($statut);
        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        $oeuvreId = max(0, (int) ($data['oeuvre_id'] ?? 0));
        if ($oeuvreId > 0) {
            $oeuvre = $this->oeuvres->findById($oeuvreId);
            if ($oeuvre === null) {
                return 'Cette œuvre n’existe plus dans le catalogue.';
            }

            return $this->attach->attachOeuvreToLibrary($oeuvreId, $data, $statut);
        }

        $realisateur = trim((string) ($data['realisateur'] ?? ''));
        $existing = $this->libraryQuery->findByTitreAndRealisateur($titre, $realisateur);
        if ($existing !== null) {
            return $this->attach->attachOeuvreToLibrary((int) $existing['oeuvre_id'], $data, $statut);
        }

        $oeuvre = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
        if ($oeuvre !== null) {
            return $this->attach->attachOeuvreToLibrary((int) $oeuvre['id'], $data, $statut);
        }

        if (!UserContext::canManageCatalog()) {
            return 'Cette œuvre n’est pas encore dans le catalogue Moncine. '
                . 'Proposez-la d’abord via « Proposer au catalogue », '
                . 'puis ajoutez-la à vos films une fois la proposition acceptée.';
        }

        $data['statut'] = $statut;
        $this->import->upsertFromExport($data, []);

        $created = $this->libraryQuery->findByTitreAndRealisateur($titre, $realisateur);
        if ($created === null) {
            return 'Impossible de créer le film.';
        }

        $libraryId = (int) $created['id'];
        $updateResult = $this->updater->updateManual($libraryId, $data);
        if ($updateResult !== true) {
            return (string) $updateResult;
        }

        return $libraryId;
    }
}

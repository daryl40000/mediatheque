<?php
/**
 * Rattachement d’une œuvre du catalogue films à la bibliothèque utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

final class FilmLibraryAttach
{
    public function __construct(
        private readonly OeuvreRepository $oeuvres,
        private readonly BibliothequeRepository $bibliotheque,
        private readonly FilmCatalogUpdater $updater,
        private readonly FilmCatalogSaga $saga
    ) {
    }

    /**
     * Ajoute une œuvre déjà au catalogue à la bibliothèque (sans formulaire détaillé).
     *
     * @return int|string ID bibliothèque ou message d’erreur
     */
    public function addFromCatalogOeuvre(int $oeuvreId, string $statut): int|string
    {
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        $oeuvre = $this->oeuvres->findById($oeuvreId);
        if ($oeuvre === null) {
            return 'Cette œuvre n’existe plus dans le catalogue.';
        }

        return $this->attachOeuvreToLibrary($oeuvreId, [
            'oeuvre_id' => $oeuvreId,
            'titre' => (string) ($oeuvre['titre'] ?? ''),
            'realisateur' => (string) ($oeuvre['realisateur'] ?? ''),
            'annee' => (int) ($oeuvre['annee'] ?? 0),
            'moncine_kind' => (string) ($oeuvre['moncine_kind'] ?? ''),
        ], LibraryStatut::normalize($statut));
    }

    /**
     * Relie une œuvre du catalogue à la bibliothèque de l’utilisateur (nouvelle entrée ou changement de statut).
     *
     * @param array<string, mixed> $data
     * @return int|string ID bibliothèque ou message d’erreur
     */
    public function attachOeuvreToLibrary(int $oeuvreId, array $data, string $statut): int|string
    {
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        $oeuvre = $this->oeuvres->findById($oeuvreId);
        if ($oeuvre === null) {
            return 'Œuvre introuvable dans le catalogue.';
        }

        $userId = $this->userId();
        $foyerId = $this->foyerId();

        $library = $this->bibliotheque->findByOeuvreId($oeuvreId, $userId, $foyerId);
        if ($library !== null) {
            $libraryId = (int) $library['id'];
            $currentStatut = (string) ($library['statut'] ?? LibraryStatut::COLLECTION);
            if ($currentStatut === $statut) {
                return 'Ce film existe déjà dans « ' . LibraryStatut::label($statut) . ' ».';
            }

            $updateResult = $this->updater->updateManual($libraryId, $data);
            if ($updateResult !== true) {
                return (string) $updateResult;
            }
            $update = ['statut' => $statut];
            if ($statut === LibraryStatut::COLLECTION) {
                $update['foyer_id'] = $foyerId;
            } else {
                $update['foyer_id'] = null;
            }
            $this->bibliotheque->update($libraryId, $update);

            return $libraryId;
        }

        $libraryPayload = [
            'support_physique' => SupportPhysique::normalize((string) ($data['support_physique'] ?? '')),
            'format_image' => trim((string) ($data['format_image'] ?? '')),
            'format_son' => trim((string) ($data['format_son'] ?? '')),
            'saison_numero' => max(0, (int) ($data['saison_numero'] ?? 0)),
            'saison_label' => trim((string) ($data['saison_label'] ?? '')),
            'ean' => OeuvreEanRepository::normalizeEan((string) ($data['ean'] ?? '')),
            'statut' => $statut,
        ];
        [$libraryPayload['saga'], $libraryPayload['saga_ordre']] = $this->saga->resolveLibrarySagaFromOeuvre($oeuvre, $data);

        $libraryId = $this->bibliotheque->insert($userId, $foyerId, $oeuvreId, $libraryPayload);
        $updateResult = $this->updater->updateManual($libraryId, $data);
        if ($updateResult !== true) {
            $this->bibliotheque->deleteById($libraryId, $userId, $foyerId);

            return (string) $updateResult;
        }

        return $libraryId;
    }

    private function userId(): int
    {
        return UserContext::currentUserId();
    }

    private function foyerId(): int
    {
        return UserContext::currentFoyerId();
    }
}

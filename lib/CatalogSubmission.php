<?php
/**
 * Propositions d’œuvres au catalogue (utilisateurs → validation admin).
 */

declare(strict_types=1);

namespace Moncine;

final class CatalogSubmission
{
    private CatalogSubmissionRepository $repo;

    private OeuvreRepository $oeuvres;

    public function __construct()
    {
        $this->repo = new CatalogSubmissionRepository();
        $this->oeuvres = new OeuvreRepository();
    }

    public static function isAvailable(): bool
    {
        return CatalogSchema::usesCatalogTables(Database::getInstance())
            && CatalogSubmissionRepository::tableExists();
    }

    public static function denyUnlessAvailable(): void
    {
        if (!self::isAvailable()) {
            header('Location: /');
            exit;
        }
    }

    /** Les administrateurs ajoutent directement au catalogue ; seuls les autres utilisateurs proposent. */
    public static function canSubmit(): bool
    {
        return self::isAvailable() && !UserContext::canManageCatalog();
    }

    public static function denyUnlessSubmitter(): void
    {
        self::denyUnlessAvailable();
        if (!self::canSubmit()) {
            header('Location: /catalogue.php');
            exit;
        }
    }

    public function countPending(): int
    {
        return $this->repo->countPending();
    }

    /**
     * @param array<string, mixed> $manualEditData
     * @return int|string ID proposition ou message d’erreur
     */
    public function submit(int $userId, array $manualEditData, string $userNote = ''): int|string
    {
        if (UserContext::canManageCatalog()) {
            return 'Les administrateurs ajoutent les œuvres directement depuis le catalogue.';
        }

        if (!isset($manualEditData['submission_domain'])) {
            $manualEditData['submission_domain'] = MediaDomain::FILM;
        }

        $isGame = CatalogSubmissionPayload::isGame($manualEditData);
        if ($isGame && !GameRepository::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        $titre = trim((string) ($manualEditData['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        if ($isGame) {
            $existing = $this->oeuvres->findByTitreRealisateurAndDomain(
                $titre,
                '',
                MediaDomain::JEU
            );
            if ($existing !== null) {
                return 'Ce jeu est déjà au catalogue. Ajoutez-le depuis Mes jeux ou Mes envies.';
            }
        } else {
            $realisateur = trim((string) ($manualEditData['realisateur'] ?? ''));
            if ($this->oeuvres->findByTitreAndRealisateur($titre, $realisateur) !== null) {
                return 'Cette œuvre est déjà au catalogue. Ajoutez-la depuis Mes films ou Mes envies.';
            }
        }

        try {
            $json = CatalogSubmissionPayload::encode($manualEditData);
        } catch (\JsonException) {
            return 'Impossible d’enregistrer la proposition.';
        }

        $submissionId = $this->repo->insert($userId, $json, $userNote);
        if ($submissionId > 0) {
            $submitter = (new UtilisateurRepository())->findById($userId);
            $label = $submitter !== null
                ? View::userDisplayName($submitter)
                : 'Un utilisateur';
            (new NotificationService())->notifyAdminsNewSubmission(
                $submissionId,
                $titre,
                $label
            );
        }

        return $submissionId;
    }

    /**
     * @param array<string, mixed> $manualEditData
     * @return true|string
     */
    public function updatePendingPayload(int $submissionId, array $manualEditData): bool|string
    {
        $row = $this->repo->findById($submissionId);
        if ($row === null) {
            return 'Proposition introuvable.';
        }
        if ((string) ($row['status'] ?? '') !== CatalogSubmissionRepository::STATUS_PENDING) {
            return 'Cette proposition n’est plus modifiable.';
        }

        $titre = trim((string) ($manualEditData['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }

        try {
            $json = CatalogSubmissionPayload::encode($manualEditData);
        } catch (\JsonException) {
            return 'Impossible d’enregistrer les modifications.';
        }

        if (!$this->repo->updatePayload($submissionId, $json)) {
            return 'Impossible de mettre à jour la proposition.';
        }

        return true;
    }

    /**
     * @param array<string, mixed> $manualEditData Données validées par l’admin
     * @return int|string ID œuvre créée ou message d’erreur
     */
    public function approve(
        int $submissionId,
        int $adminId,
        array $manualEditData,
        string $reviewNote = '',
        bool $enrichAfter = false
    ): int|string {
        $row = $this->repo->findById($submissionId);
        if ($row === null) {
            return 'Proposition introuvable.';
        }
        if ((string) ($row['status'] ?? '') !== CatalogSubmissionRepository::STATUS_PENDING) {
            return 'Cette proposition a déjà été traitée.';
        }

        $isGame = CatalogSubmissionPayload::isGame($manualEditData);
        if ($isGame) {
            if (!GameRepository::isAvailable()) {
                return 'Module jeux non disponible.';
            }
            $createData = CatalogSubmissionPayload::toCreateGameData(
                CatalogSubmissionPayload::fromManualEditData($manualEditData)
            );
            $oeuvreId = (new CatalogAdmin())->createGameOeuvre($createData);
        } else {
            $createData = CatalogSubmissionPayload::toCreateOeuvreData(
                CatalogSubmissionPayload::fromManualEditData($manualEditData)
            );
            $oeuvreId = (new CatalogAdmin())->createOeuvre($createData);
        }
        if (!is_int($oeuvreId)) {
            return $oeuvreId;
        }

        if (!$isGame && $enrichAfter && FilmEnricher::canEnrich()) {
            (new FilmEnricher())->enrichOeuvre($oeuvreId);
        }
        if ($isGame && $enrichAfter && GameEnricher::canEnrich()) {
            (new GameEnricher())->enrichOeuvre($oeuvreId);
        }

        if (!$this->repo->markApproved($submissionId, $oeuvreId, $adminId, $reviewNote)) {
            return 'La proposition a été créée au catalogue mais son statut n’a pas pu être mis à jour.';
        }

        $this->notifySubmitterReviewResult(
            $row,
            CatalogSubmissionRepository::STATUS_APPROVED,
            $oeuvreId,
            $reviewNote
        );

        return $oeuvreId;
    }

    /** @return true|string */
    public function reject(int $submissionId, int $adminId, string $reviewNote = ''): bool|string
    {
        $row = $this->repo->findById($submissionId);
        if ($row === null) {
            return 'Proposition introuvable.';
        }
        if ((string) ($row['status'] ?? '') !== CatalogSubmissionRepository::STATUS_PENDING) {
            return 'Cette proposition a déjà été traitée.';
        }

        if (!$this->repo->markRejected($submissionId, $adminId, $reviewNote)) {
            return 'Impossible de rejeter cette proposition.';
        }

        $this->notifySubmitterReviewResult(
            $row,
            CatalogSubmissionRepository::STATUS_REJECTED,
            0,
            $reviewNote
        );

        return true;
    }

    /**
     * @param array<string, mixed> $submissionRow
     */
    private function notifySubmitterReviewResult(
        array $submissionRow,
        string $status,
        int $oeuvreId,
        string $reviewNote
    ): void {
        $userId = (int) ($submissionRow['user_id'] ?? 0);
        $submissionId = (int) ($submissionRow['id'] ?? 0);
        if ($userId <= 0 || $submissionId <= 0) {
            return;
        }

        try {
            $payload = CatalogSubmissionPayload::decode((string) ($submissionRow['payload_json'] ?? ''));
        } catch (\JsonException) {
            $payload = [];
        }

        $titre = trim((string) ($payload['titre'] ?? ''));
        $notifier = new NotificationService();

        if ($status === CatalogSubmissionRepository::STATUS_APPROVED) {
            $notifier->notifyUserSubmissionApproved(
                $userId,
                $submissionId,
                $oeuvreId,
                $titre,
                $reviewNote
            );

            return;
        }

        if ($status === CatalogSubmissionRepository::STATUS_REJECTED) {
            $notifier->notifyUserSubmissionRejected(
                $userId,
                $submissionId,
                $titre,
                $reviewNote
            );
        }
    }

    /** @return array<string, mixed>|null */
    public function findForAdmin(int $id): ?array
    {
        $row = $this->repo->findById($id);
        if ($row === null) {
            return null;
        }

        $user = (new UtilisateurRepository())->findById((int) ($row['user_id'] ?? 0));
        $row['submitter'] = $user;
        try {
            $row['payload'] = CatalogSubmissionPayload::decode((string) ($row['payload_json'] ?? ''));
        } catch (\JsonException) {
            $row['payload'] = [];
        }
        $row['form_prefill'] = CatalogSubmissionPayload::toFormPrefill($row['payload']);
        $row['submission_domain'] = CatalogSubmissionPayload::domain($row['payload']);

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingForAdmin(): array
    {
        $rows = $this->repo->listPending();
        foreach ($rows as &$row) {
            try {
                $payload = CatalogSubmissionPayload::decode((string) ($row['payload_json'] ?? ''));
            } catch (\JsonException) {
                $payload = [];
            }
            $row['payload'] = $payload;
            $row['submission_domain'] = CatalogSubmissionPayload::domain($payload);
            $row['submitter_label'] = View::userDisplayName([
                'nom' => (string) ($row['submitter_nom'] ?? ''),
                'prenom' => (string) ($row['submitter_prenom'] ?? ''),
                'pseudo' => (string) ($row['submitter_pseudo'] ?? ''),
                'email' => (string) ($row['submitter_email'] ?? ''),
            ]);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $rows = $this->repo->listForUser($userId);
        foreach ($rows as &$row) {
            try {
                $row['payload'] = CatalogSubmissionPayload::decode((string) ($row['payload_json'] ?? ''));
            } catch (\JsonException) {
                $row['payload'] = [];
            }
        }
        unset($row);

        return $rows;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            CatalogSubmissionRepository::STATUS_APPROVED => 'Acceptée',
            CatalogSubmissionRepository::STATUS_REJECTED => 'Refusée',
            default => 'En attente',
        };
    }
}

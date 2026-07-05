<?php
/**
 * Création et envoi des notifications (in-app + e-mail optionnel).
 */

declare(strict_types=1);

namespace Moncine;

final class NotificationService
{
    private NotificationRepository $repo;

    public function __construct()
    {
        $this->repo = new NotificationRepository();
    }

    public static function isAvailable(): bool
    {
        return NotificationRepository::tableExists();
    }

    public function countUnread(int $userId): int
    {
        if (!self::isAvailable() || $userId <= 0) {
            return 0;
        }

        return $this->repo->countUnread($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        return $this->repo->listForUser($userId);
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        return $this->repo->markRead($notificationId, $userId);
    }

    public function markAllRead(int $userId): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        return $this->repo->markAllRead($userId);
    }

    /** Notifie tous les administrateurs actifs d’une nouvelle proposition. */
    public function notifyAdminsNewSubmission(
        int $submissionId,
        string $titre,
        string $submitterLabel
    ): void {
        if (!self::isAvailable() || !CatalogSubmission::isAvailable()) {
            return;
        }

        $titre = trim($titre) !== '' ? trim($titre) : 'Œuvre sans titre';
        $link = '/soumissions-catalogue.php?id=' . $submissionId;
        $title = 'Nouvelle proposition au catalogue';
        $body = $submitterLabel . ' propose « ' . $titre . ' ».';

        foreach ((new UtilisateurRepository())->listActiveAdmins() as $admin) {
            $adminId = (int) ($admin['id'] ?? 0);
            if ($adminId <= 0) {
                continue;
            }

            $this->repo->insert(
                $adminId,
                NotificationRepository::KIND_SUBMISSION_NEW,
                $title,
                $body,
                $link,
                $submissionId
            );

            $email = trim((string) ($admin['email'] ?? ''));
            if ($email !== '') {
                MailService::sendCatalogSubmissionNewToAdmin(
                    $email,
                    View::userDisplayName($admin),
                    $titre,
                    $submitterLabel,
                    AppUrl::path($link)
                );
            }
        }
    }

    /** Notifie les administrateurs d’une inscription confirmée par e-mail, en attente de validation. */
    public function notifyAdminsRegistrationPending(
        int $requestId,
        string $applicantLabel,
        string $applicantEmail
    ): void {
        if (!self::isAvailable() || !RegistrationService::isAvailable()) {
            return;
        }

        $link = '/demandes-inscription.php?id=' . $requestId;
        $title = 'Inscription à valider';
        $body = $applicantLabel . ' (' . $applicantEmail . ') a confirmé son e-mail.';

        foreach ((new UtilisateurRepository())->listActiveAdmins() as $admin) {
            $adminId = (int) ($admin['id'] ?? 0);
            if ($adminId <= 0) {
                continue;
            }

            $this->repo->insert(
                $adminId,
                NotificationRepository::KIND_REGISTRATION_PENDING,
                $title,
                $body,
                $link
            );

            $email = trim((string) ($admin['email'] ?? ''));
            if ($email !== '') {
                MailService::sendRegistrationPendingToAdmin(
                    $email,
                    View::userDisplayName($admin),
                    $applicantLabel,
                    $applicantEmail,
                    AppUrl::path('/demandes-inscription.php')
                );
            }
        }
    }

    public function notifyUserSubmissionApproved(
        int $userId,
        int $submissionId,
        int $oeuvreId,
        string $titre,
        string $reviewNote = '',
        ?int $libraryBibId = null
    ): void {
        if (!self::isAvailable() || $userId <= 0) {
            return;
        }

        $titre = trim($titre);
        if ($titre === '' && $oeuvreId > 0) {
            $oeuvre = (new OeuvreRepository())->findByIdForAdmin($oeuvreId);
            $titre = trim((string) ($oeuvre['titre'] ?? ''));
        }
        if ($titre === '') {
            $titre = 'votre proposition';
        }

        $mediaDomain = MediaDomain::FILM;
        if ($oeuvreId > 0) {
            $oeuvre = (new OeuvreRepository())->findByIdForAdmin($oeuvreId);
            $mediaDomain = (string) ($oeuvre['media_domain'] ?? MediaDomain::FILM);
        }

        $link = $oeuvreId > 0
            ? (MediaDomain::isGame($mediaDomain)
                ? ($libraryBibId !== null && $libraryBibId > 0
                    ? View::gameUrl($libraryBibId)
                    : View::addGameChoiceUrl($oeuvreId))
                : View::addFilmChoiceUrl($oeuvreId))
            : '/mes-soumissions.php';
        $title = 'Proposition acceptée — ' . $titre;
        if (MediaDomain::isGame($mediaDomain)) {
            $body = '« ' . $titre . ' » est dans le catalogue jeux.';
            if ($libraryBibId !== null && $libraryBibId > 0) {
                $body .= ' Il a été ajouté automatiquement à Mes jeux (import Steam).';
            } else {
                $body .= ' Cliquez pour l’ajouter à Mes jeux (collection) ou à Mes envies jeux.';
            }
        } else {
            $body = '« ' . $titre . ' » est dans le catalogue Moncine.';
            $body .= ' Cliquez pour l’ajouter à Mes films (collection) ou à Mes envies.';
        }
        if (trim($reviewNote) !== '') {
            $body .= "\n\nMessage de l’administrateur : " . trim($reviewNote);
        }

        $this->repo->insert(
            $userId,
            NotificationRepository::KIND_SUBMISSION_APPROVED,
            $title,
            $body,
            $link,
            $submissionId,
            $oeuvreId > 0 ? $oeuvreId : null
        );

        $this->sendUserEmail($userId, $title, $body, $link);
    }

    public function notifyUserSubmissionRejected(
        int $userId,
        int $submissionId,
        string $titre,
        string $reviewNote = ''
    ): void {
        if (!self::isAvailable() || $userId <= 0) {
            return;
        }

        $titre = trim($titre) !== '' ? trim($titre) : 'votre proposition';
        $link = '/mes-soumissions.php';
        $title = 'Proposition refusée';
        $body = '« ' . $titre . ' » n’a pas été ajoutée au catalogue.';
        if (trim($reviewNote) !== '') {
            $body .= "\n\nMessage : " . trim($reviewNote);
        } else {
            $body .= ' Consultez Mes propositions pour plus de détails.';
        }

        $this->repo->insert(
            $userId,
            NotificationRepository::KIND_SUBMISSION_REJECTED,
            $title,
            $body,
            $link,
            $submissionId
        );

        $this->sendUserEmail($userId, $title, $body, $link);
    }

    public function notifyFriendRequest(int $addresseeId, int $requesterId): void
    {
        if (!self::isAvailable() || !FriendshipRepository::isAvailable()) {
            return;
        }
        $requester = (new UtilisateurRepository())->findById($requesterId);
        if ($requester === null) {
            return;
        }
        $label = View::userDisplayName($requester);
        $title = 'Nouvelle demande d’ami';
        $body = $label . ' souhaite vous ajouter comme ami.';
        $link = '/mes-amis.php';

        $this->repo->insert($addresseeId, NotificationRepository::KIND_FRIEND_REQUEST, $title, $body, $link);
        $this->sendUserEmail($addresseeId, $title, $body, $link);
    }

    public function notifyFriendAccepted(int $requesterId, int $accepterId): void
    {
        if (!self::isAvailable() || !FriendshipRepository::isAvailable()) {
            return;
        }
        $accepter = (new UtilisateurRepository())->findById($accepterId);
        if ($accepter === null) {
            return;
        }
        $label = View::userDisplayName($accepter);
        $title = 'Demande d’ami acceptée';
        $body = $label . ' a accepté votre demande d’ami.';
        $link = '/mes-amis.php';

        $this->repo->insert($requesterId, NotificationRepository::KIND_FRIEND_ACCEPTED, $title, $body, $link);
        $this->sendUserEmail($requesterId, $title, $body, $link);
    }

    public function notifyGroupInvitation(
        int $inviteeId,
        int $inviterId,
        string $groupName,
        int $invitationId
    ): void {
        if (!self::isAvailable() || !FamilyGroupService::isAvailable()) {
            return;
        }
        $inviter = (new UtilisateurRepository())->findById($inviterId);
        if ($inviter === null) {
            return;
        }
        $label = View::userDisplayName($inviter);
        $groupName = trim($groupName) !== '' ? trim($groupName) : 'un groupe famille';
        $title = 'Invitation à un groupe famille';
        $body = $label . ' vous invite à rejoindre « ' . $groupName . ' ».';
        $link = '/mes-groupes.php';

        $this->repo->insert($inviteeId, NotificationRepository::KIND_GROUP_INVITE, $title, $body, $link);
        $this->sendUserEmail($inviteeId, $title, $body, $link);
    }

    public function notifyLoanRequested(int $ownerUserId, int $requesterUserId, string $filmTitle, string $mediaDomain = ''): void
    {
        if (!self::isAvailable() || $ownerUserId <= 0 || $requesterUserId <= 0) {
            return;
        }
        $requester = (new UtilisateurRepository())->findById($requesterUserId);
        if ($requester === null) {
            return;
        }
        $label = View::userDisplayName($requester);
        $filmTitle = trim($filmTitle) !== '' ? trim($filmTitle) : self::loanTitleFallback($mediaDomain);
        $title = 'Demande de prêt';
        $body = $label . ' souhaite vous emprunter « ' . $filmTitle . ' ».';
        $link = '/mes-prets.php';

        $this->repo->insert($ownerUserId, NotificationRepository::KIND_LOAN_REQUEST, $title, $body, $link);
        $this->sendUserEmail($ownerUserId, $title, $body, $link);
    }

    public function notifyLoanAccepted(int $requesterUserId, int $ownerUserId, string $filmTitle, string $mediaDomain = ''): void
    {
        if (!self::isAvailable() || $requesterUserId <= 0 || $ownerUserId <= 0) {
            return;
        }
        $owner = (new UtilisateurRepository())->findById($ownerUserId);
        if ($owner === null) {
            return;
        }
        $label = View::userDisplayName($owner);
        $filmTitle = trim($filmTitle) !== '' ? trim($filmTitle) : self::loanTitleFallback($mediaDomain);
        $title = 'Prêt accepté (réservé)';
        $body = $label . ' a accepté votre demande pour « ' . $filmTitle . ' » (exemplaire réservé).';
        $link = View::userProfileUrl($ownerUserId);

        $this->repo->insert($requesterUserId, NotificationRepository::KIND_LOAN_ACCEPTED, $title, $body, $link);
        $this->sendUserEmail($requesterUserId, $title, $body, $link);
    }

    public function notifyLoanDeclined(int $requesterUserId, int $ownerUserId, string $filmTitle, string $mediaDomain = ''): void
    {
        if (!self::isAvailable() || $requesterUserId <= 0 || $ownerUserId <= 0) {
            return;
        }
        $owner = (new UtilisateurRepository())->findById($ownerUserId);
        if ($owner === null) {
            return;
        }
        $label = View::userDisplayName($owner);
        $filmTitle = trim($filmTitle) !== '' ? trim($filmTitle) : self::loanTitleFallback($mediaDomain);
        $title = 'Prêt refusé';
        $body = $label . ' a refusé votre demande pour « ' . $filmTitle . ' ».';
        $link = View::userProfileUrl($ownerUserId);

        $this->repo->insert($requesterUserId, NotificationRepository::KIND_LOAN_DECLINED, $title, $body, $link);
        $this->sendUserEmail($requesterUserId, $title, $body, $link);
    }

    public function notifyLoanLent(int $requesterUserId, int $ownerUserId, string $filmTitle, string $mediaDomain = ''): void
    {
        if (!self::isAvailable() || $requesterUserId <= 0 || $ownerUserId <= 0) {
            return;
        }
        $owner = (new UtilisateurRepository())->findById($ownerUserId);
        if ($owner === null) {
            return;
        }
        $label = View::userDisplayName($owner);
        $filmTitle = trim($filmTitle) !== '' ? trim($filmTitle) : self::loanTitleFallback($mediaDomain);
        $title = 'Prêt validé';
        $body = $label . ' a enregistré le prêt de « ' . $filmTitle . ' ».';
        $link = '/mes-prets.php';

        $this->repo->insert($requesterUserId, NotificationRepository::KIND_LOAN_LENT, $title, $body, $link);
        $this->sendUserEmail($requesterUserId, $title, $body, $link);
    }

    public function notifyLoanReturned(int $requesterUserId, int $ownerUserId, string $filmTitle, string $mediaDomain = ''): void
    {
        if (!self::isAvailable() || $requesterUserId <= 0 || $ownerUserId <= 0) {
            return;
        }
        $owner = (new UtilisateurRepository())->findById($ownerUserId);
        if ($owner === null) {
            return;
        }
        $label = View::userDisplayName($owner);
        $filmTitle = trim($filmTitle) !== '' ? trim($filmTitle) : self::loanTitleFallback($mediaDomain);
        $title = 'Retour enregistré';
        $body = $label . ' a marqué comme rendu « ' . $filmTitle . ' ».';
        $link = '/mes-prets.php';

        $this->repo->insert($requesterUserId, NotificationRepository::KIND_LOAN_RETURNED, $title, $body, $link);
        $this->sendUserEmail($requesterUserId, $title, $body, $link);
    }

    private static function loanTitleFallback(string $mediaDomain): string
    {
        return LoanEligibility::mediaItemLabel($mediaDomain) === 'jeu' ? 'un jeu' : 'un film';
    }

    private function sendUserEmail(int $userId, string $subject, string $body, string $path): void
    {
        $user = (new UtilisateurRepository())->findById($userId);
        if ($user === null) {
            return;
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return;
        }

        MailService::sendNotification(
            $email,
            View::userDisplayName($user),
            $subject,
            $body,
            AppUrl::path($path)
        );
    }
}

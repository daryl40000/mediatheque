<?php
/**
 * Inscription publique : demande, confirmation e-mail, approbation admin.
 */

declare(strict_types=1);

namespace Moncine;

final class RegistrationService
{
    private RegistrationSettings $settings;
    private InscriptionRequestRepository $requests;
    private UtilisateurRepository $users;

    public function __construct()
    {
        $this->settings = new RegistrationSettings();
        $this->requests = new InscriptionRequestRepository();
        $this->users = new UtilisateurRepository();
    }

    public static function isAvailable(): bool
    {
        return RegistrationSettings::isAvailable();
    }

    public function settings(): RegistrationSettings
    {
        return $this->settings;
    }

    /**
     * Soumet une demande d’inscription. Retourne true si le message neutre doit être affiché.
     *
     * @return true|string true = afficher le message de succès neutre ; string = erreur formulaire
     */
    public function submitRequest(
        string $nom,
        string $email,
        string $plainPassword,
        string $prenom = '',
        string $pseudo = ''
    ): bool|string {
        if (!$this->settings->isPublicRegistrationEnabled()) {
            return 'L’inscription publique est désactivée.';
        }

        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse e-mail invalide.';
        }

        if (RegistrationThrottle::isBlocked($email)) {
            return 'Trop de tentatives. Réessayez dans quelques minutes.';
        }

        RegistrationThrottle::recordAttempt($email);

        $hash = UtilisateurRepository::hashPassword($plainPassword);
        if ($hash === null) {
            return UtilisateurRepository::passwordValidationMessage();
        }

        $identity = UserProfile::validateIdentityFields(trim($nom), trim($prenom), UserProfile::sanitizePseudo($pseudo));
        if ($identity !== true) {
            return $identity;
        }

        if ($this->users->findByEmail($email) !== null || $this->requests->hasActiveRequestForEmail($email)) {
            return true;
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expires = gmdate('Y-m-d H:i:s', time() + InscriptionRequestRepository::CONFIRM_TTL_SECONDS);

        $insert = $this->requests->insertPendingEmail(
            $nom,
            $prenom,
            $pseudo,
            $email,
            RegistrationPasswordCipher::encryptHash($hash),
            $tokenHash,
            $expires
        );
        if ($insert !== true) {
            // Course entre deux requêtes ou doublon : même comportement neutre que plus haut (sans second e-mail).
            if ($this->requests->hasActiveRequestForEmail($email)) {
                return true;
            }

            return (string) $insert;
        }

        $confirmUrl = AppUrl::path('/confirmer-inscription.php?token=' . rawurlencode($plainToken));
        $displayName = trim($prenom . ' ' . $nom);
        if ($displayName === '') {
            $displayName = $email;
        }

        MailService::sendRegistrationConfirm($email, $displayName, $confirmUrl);

        return true;
    }

    /**
     * Vérifie qu’un jeton de confirmation est valide (sans consommer la demande).
     * Utilisé pour afficher le formulaire POST (évite la confirmation automatique au GET).
     */
    public function isConfirmTokenValid(string $plainToken): bool
    {
        $this->requests->purgeExpiredPendingEmail();
        $plainToken = trim($plainToken);

        return $plainToken !== '' && $this->requests->findByConfirmToken($plainToken) !== null;
    }

    /**
     * Confirme l’adresse e-mail via le jeton (appelé uniquement en POST après action utilisateur).
     *
     * @return array{outcome: string, message: string}
     */
    public function confirmEmail(string $plainToken): array
    {
        $this->requests->purgeExpiredPendingEmail();

        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return [
                'outcome' => 'error',
                'message' => 'Lien invalide ou expiré.',
            ];
        }

        $request = $this->requests->findByConfirmToken($plainToken);
        if ($request === null) {
            return [
                'outcome' => 'error',
                'message' => 'Lien invalide ou expiré. Vous pouvez refaire une demande d’inscription si besoin.',
            ];
        }

        $requestId = (int) ($request['id'] ?? 0);
        $email = (string) ($request['email'] ?? '');

        if ($this->users->findByEmail($email) !== null) {
            return [
                'outcome' => 'error',
                'message' => 'Un compte existe déjà pour cette adresse. Connectez-vous.',
            ];
        }

        if ($this->settings->requiresAdminApproval()) {
            if (!$this->requests->markEmailConfirmed($requestId, InscriptionRequestRepository::STATUS_PENDING_ADMIN)) {
                return [
                    'outcome' => 'error',
                    'message' => 'Cette demande a déjà été traitée.',
                ];
            }

            $displayName = $this->requestDisplayName($request);
            (new NotificationService())->notifyAdminsRegistrationPending($requestId, $displayName, $email);
            MailService::sendRegistrationAwaitingAdmin($email, $displayName);

            return [
                'outcome' => 'pending_admin',
                'message' => 'Votre adresse e-mail est confirmée. Un administrateur doit valider votre compte avant la connexion.',
            ];
        }

        $userId = $this->createUserFromRequest($request);
        if (!is_int($userId)) {
            return [
                'outcome' => 'error',
                'message' => (string) $userId,
            ];
        }

        if (!$this->requests->markApprovedDirect($requestId, $userId)) {
            return [
                'outcome' => 'error',
                'message' => 'Compte créé mais la demande n’a pas pu être finalisée. Contactez l’administrateur.',
            ];
        }

        MailService::sendRegistrationAccountReady($email, $this->requestDisplayName($request));

        return [
            'outcome' => 'ready',
            'message' => 'Votre compte est prêt. Vous pouvez vous connecter avec l’e-mail et le mot de passe choisis à l’inscription.',
        ];
    }

    /**
     * @return true|string
     */
    public function approve(int $requestId, int $adminId, string $reviewNote = ''): bool|string
    {
        $request = $this->requests->findById($requestId);
        if ($request === null || (string) ($request['status'] ?? '') !== InscriptionRequestRepository::STATUS_PENDING_ADMIN) {
            return 'Demande introuvable ou déjà traitée.';
        }

        $email = (string) ($request['email'] ?? '');
        if ($this->users->findByEmail($email) !== null) {
            return 'Un compte existe déjà pour cette adresse e-mail.';
        }

        $userId = $this->createUserFromRequest($request);
        if (!is_int($userId)) {
            return (string) $userId;
        }

        if (!$this->requests->markApproved($requestId, $userId, $adminId, $reviewNote)) {
            return 'Impossible de finaliser la demande.';
        }

        MailService::sendRegistrationAccountReady($email, $this->requestDisplayName($request));

        return true;
    }

    /**
     * @return true|string
     */
    public function reject(int $requestId, int $adminId, string $reviewNote = ''): bool|string
    {
        $request = $this->requests->findById($requestId);
        if ($request === null || (string) ($request['status'] ?? '') !== InscriptionRequestRepository::STATUS_PENDING_ADMIN) {
            return 'Demande introuvable ou déjà traitée.';
        }

        if (!$this->requests->markRejected($requestId, $adminId, $reviewNote)) {
            return 'Refus impossible.';
        }

        $email = (string) ($request['email'] ?? '');
        MailService::sendRegistrationRejected($email, $this->requestDisplayName($request), $reviewNote);

        return true;
    }

    public function countPendingAdmin(): int
    {
        return $this->requests->countPendingAdmin();
    }

    /** @return list<array<string, mixed>> */
    public function listPendingAdmin(): array
    {
        return $this->requests->listPendingAdmin();
    }

    /** @return int|string */
    private function createUserFromRequest(array $request): int|string
    {
        $passwordHash = RegistrationPasswordCipher::decryptStored((string) ($request['password_hash'] ?? ''));
        if ($passwordHash === null || $passwordHash === '') {
            return 'Demande invalide ou expirée. Refaites une inscription si besoin.';
        }

        $userId = $this->users->createWithPasswordHash(
            (string) ($request['nom'] ?? ''),
            (string) ($request['email'] ?? ''),
            $passwordHash,
            UserRole::USER,
            (string) ($request['prenom'] ?? ''),
            (string) ($request['pseudo'] ?? '')
        );

        if (!is_int($userId)) {
            return $userId;
        }

        return $userId;
    }

    /** @param array<string, mixed> $request */
    private function requestDisplayName(array $request): string
    {
        $prenom = trim((string) ($request['prenom'] ?? ''));
        $nom = trim((string) ($request['nom'] ?? ''));
        $name = trim($prenom . ' ' . $nom);
        if ($name !== '') {
            return $name;
        }

        $pseudo = trim((string) ($request['pseudo'] ?? ''));

        return $pseudo !== '' ? $pseudo : (string) ($request['email'] ?? '');
    }
}

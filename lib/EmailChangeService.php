<?php
/**
 * Changement d’adresse e-mail avec confirmation sur la nouvelle adresse.
 */

declare(strict_types=1);

namespace Moncine;

final class EmailChangeService
{
    /**
     * @return true|string true = autres champs mis à jour ; e-mail en attente de confirmation
     */
    public function requestChange(
        int $userId,
        string $currentPassword,
        string $newEmail,
        string $nom,
        string $prenom,
        string $pseudo,
        string $ville,
        bool $searchable
    ): bool|string {
        $users = new UtilisateurRepository();
        $user = $users->findByIdForAuthentication($userId);
        if ($user === null) {
            return 'Compte introuvable.';
        }

        if (!UtilisateurRepository::verifyPassword($user, $currentPassword)) {
            return 'Mot de passe incorrect.';
        }

        $oldEmail = mb_strtolower(trim((string) ($user['email'] ?? '')), 'UTF-8');
        $newEmail = mb_strtolower(trim($newEmail), 'UTF-8');

        $profile = $users->updateProfileWithoutEmail(
            $userId,
            $nom,
            $prenom,
            $pseudo,
            $ville,
            $searchable
        );
        if ($profile !== true) {
            return $profile;
        }

        if ($newEmail === $oldEmail) {
            return true;
        }

        $existing = $users->findByEmail($newEmail);
        if ($existing !== null && (int) ($existing['id'] ?? 0) !== $userId) {
            return 'Cette adresse e-mail est déjà utilisée.';
        }

        $repo = new EmailChangeRepository();
        $plain = $repo->create($userId, $oldEmail, $newEmail);
        if (!is_string($plain)) {
            return $plain;
        }

        $confirmUrl = AppUrl::path('/confirmer-email.php?token=' . rawurlencode($plain));
        $displayName = UserProfile::displayName($user);

        MailService::sendEmailChangeConfirm($newEmail, $displayName, $confirmUrl);
        MailService::sendEmailChangeNoticeOld($oldEmail, $displayName, $newEmail);

        return 'Un lien de confirmation a été envoyé à la nouvelle adresse. L’ancienne adresse a été notifiée.';
    }

    /**
     * @return array{outcome: string, message: string}
     */
    public function confirm(string $plainToken): array
    {
        $repo = new EmailChangeRepository();
        $row = $repo->findRowByToken($plainToken);
        if ($row === null) {
            return [
                'outcome' => 'error',
                'message' => 'Lien invalide ou expiré.',
            ];
        }

        $userId = (int) ($row['user_id'] ?? 0);
        $newEmail = (string) ($row['new_email'] ?? '');
        $users = new UtilisateurRepository();

        $other = $users->findByEmail($newEmail);
        if ($other !== null && (int) ($other['id'] ?? 0) !== $userId) {
            $repo->consume($userId);

            return [
                'outcome' => 'error',
                'message' => 'Cette adresse e-mail est déjà utilisée.',
            ];
        }

        $applied = $users->applyEmailChange($userId, $newEmail);
        if ($applied !== true) {
            return [
                'outcome' => 'error',
                'message' => (string) $applied,
            ];
        }

        $repo->consume($userId);

        return [
            'outcome' => 'ready',
            'message' => 'Votre adresse e-mail a été mise à jour. Vous pouvez vous connecter avec la nouvelle adresse.',
        ];
    }
}

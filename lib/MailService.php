<?php
/**
 * Envoi d’e-mails simples (réinitialisation mot de passe).
 *
 * Utilise la fonction PHP mail() — sur YunoHost, le serveur doit pouvoir envoyer des mails.
 * Expéditeur optionnel : variable d’environnement MONCINE_MAIL_FROM (voir extra_php-fpm.conf).
 */

declare(strict_types=1);

namespace Moncine;

final class MailService
{
    public static function sendRegistrationConfirm(string $toEmail, string $nom, string $confirmUrl): bool
    {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — Confirmez votre inscription';
        $body = "Bonjour " . $nom . ",\n\n"
            . "Pour finaliser votre inscription sur " . $app . ", ouvrez le lien ci-dessous "
            . "(valable 48 heures), puis cliquez sur le bouton de confirmation sur la page :\n\n"
            . $confirmUrl . "\n\n"
            . "Si vous n’avez pas demandé ce compte, ignorez ce message.\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendRegistrationAwaitingAdmin(string $toEmail, string $nom): bool
    {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — Inscription en attente de validation';
        $body = "Bonjour " . $nom . ",\n\n"
            . "Votre adresse e-mail est confirmée. Un administrateur doit encore approuver votre compte "
            . "sur " . $app . ". Vous recevrez un message lorsque vous pourrez vous connecter.\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendRegistrationAccountReady(string $toEmail, string $nom): bool
    {
        $app = MONCINE_APP_NAME;
        $loginUrl = AppUrl::path('/connexion.php');
        $subject = $app . ' — Votre compte est prêt';
        $body = "Bonjour " . $nom . ",\n\n"
            . "Votre compte sur " . $app . " est actif. Connectez-vous avec l’e-mail et le mot de passe "
            . "que vous avez choisis à l’inscription :\n\n"
            . $loginUrl . "\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendRegistrationRejected(string $toEmail, string $nom, string $reviewNote = ''): bool
    {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — Demande d’inscription refusée';
        $body = "Bonjour " . $nom . ",\n\n"
            . "Votre demande d’inscription sur " . $app . " n’a pas été acceptée.";
        if (trim($reviewNote) !== '') {
            $body .= "\n\nMessage de l’administrateur : " . trim($reviewNote);
        }
        $body .= "\n\n— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendRegistrationPendingToAdmin(
        string $toEmail,
        string $adminName,
        string $applicantLabel,
        string $applicantEmail,
        string $reviewUrl
    ): bool {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — Nouvelle inscription à valider';
        $body = "Bonjour " . $adminName . ",\n\n"
            . $applicantLabel . " (" . $applicantEmail . ") a confirmé son e-mail et attend votre validation.\n\n"
            . "Traiter les demandes :\n" . $reviewUrl . "\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendEmailChangeConfirm(string $toEmail, string $nom, string $confirmUrl): bool
    {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — Confirmez votre nouvelle adresse e-mail';
        $body = "Bonjour " . $nom . ",\n\n"
            . "Vous avez demandé à changer l’adresse e-mail de votre compte sur " . $app . ".\n"
            . "Pour confirmer, ouvrez le lien ci-dessous (valable 1 heure) :\n\n"
            . $confirmUrl . "\n\n"
            . "Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendEmailChangeNoticeOld(string $toEmail, string $nom, string $newEmail): bool
    {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — Changement d’adresse e-mail demandé';
        $body = "Bonjour " . $nom . ",\n\n"
            . "Une demande de changement d’adresse e-mail a été enregistrée sur votre compte " . $app . ".\n"
            . "Nouvelle adresse demandée : " . $newEmail . "\n\n"
            . "La modification ne sera effective qu’après confirmation via un lien envoyé à cette nouvelle adresse.\n"
            . "Si vous n’êtes pas à l’origine de cette demande, connectez-vous et changez votre mot de passe.\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendPasswordReset(string $toEmail, string $nom, string $resetUrl): bool
    {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — Réinitialisation de votre mot de passe';
        $body = "Bonjour " . $nom . ",\n\n"
            . "Vous avez demandé à réinitialiser votre mot de passe sur " . $app . ".\n"
            . "Cliquez sur le lien ci-dessous (valable 1 heure, usage unique) :\n\n"
            . $resetUrl . "\n\n"
            . "Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendCatalogSubmissionNewToAdmin(
        string $toEmail,
        string $adminName,
        string $titre,
        string $submitterLabel,
        string $reviewUrl
    ): bool {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — Nouvelle proposition au catalogue';
        $body = "Bonjour " . $adminName . ",\n\n"
            . $submitterLabel . " a proposé l’œuvre « " . $titre . " » pour le catalogue.\n\n"
            . "Examiner la proposition :\n" . $reviewUrl . "\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function sendNotification(
        string $toEmail,
        string $userName,
        string $subjectLine,
        string $messageBody,
        string $appUrl
    ): bool {
        $app = MONCINE_APP_NAME;
        $subject = $app . ' — ' . $subjectLine;
        $body = "Bonjour " . $userName . ",\n\n"
            . $messageBody . "\n\n"
            . "Voir dans Moncine :\n" . $appUrl . "\n\n"
            . "— " . $app;

        return self::send($toEmail, $subject, $body);
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $from = getenv('MONCINE_MAIL_FROM');
        if (!is_string($from) || $from === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $from = 'noreply@' . preg_replace('/[^a-z0-9.-]/i', '', $host);
        }

        $headers = [
            'From: ' . $from,
            'Content-Type: text/plain; charset=UTF-8',
            'MIME-Version: 1.0',
        ];

        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
        if (!$ok) {
            error_log('Moncine mail() failed for ' . $to);
        }

        return $ok;
    }
}

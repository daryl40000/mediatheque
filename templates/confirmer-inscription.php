<?php
/**
 * @var string $token
 * @var bool $tokenValid
 * @var string $outcome
 * @var string $message
 * @var bool $confirmed
 */
?>
<section class="auth-page">
    <h1>Confirmation d’inscription</h1>

    <?php if ($confirmed): ?>
        <?php if ($outcome === 'ready'): ?>
            <p class="alert alert-success"><?= Moncine\View::escape($message) ?></p>
            <p><a class="btn btn-primary" href="/connexion.php?confirmed=1">Aller à la connexion</a></p>
        <?php elseif ($outcome === 'pending_admin'): ?>
            <p class="alert alert-success"><?= Moncine\View::escape($message) ?></p>
            <p><a class="btn btn-secondary" href="/connexion.php?pending_admin=1">Retour à la connexion</a></p>
        <?php else: ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($message) ?></p>
        <?php endif; ?>
    <?php elseif ($tokenValid): ?>
        <p class="lead">
            Pour finaliser votre inscription, cliquez sur le bouton ci-dessous.
            (Cette étape évite une confirmation automatique par les filtres anti-spam de votre messagerie.)
        </p>

        <form method="post" action="/confirmer-inscription.php" class="auth-form import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="token" value="<?= Moncine\View::escape($token) ?>">
            <button type="submit" class="btn btn-primary">Confirmer mon adresse e-mail</button>
        </form>
    <?php else: ?>
        <p class="alert alert-warning">
            <?= Moncine\View::escape($message !== '' ? $message : 'Lien invalide ou expiré.') ?>
        </p>
        <p class="hint">
            <a href="/connexion.php">Connexion</a>
            <?php if (Moncine\RegistrationService::isAvailable() && (new Moncine\RegistrationSettings())->isPublicRegistrationEnabled()): ?>
                · <a href="/inscription.php">Nouvelle inscription</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>

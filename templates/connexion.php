<?php
/** @var string $error */
/** @var string $redirect */
/** @var bool $registrationEnabled */
/** @var bool $flashRegistered */
/** @var bool $flashConfirmed */
/** @var bool $flashPendingAdmin */
/** @var bool $flashAccountDeleted */
/** @var bool $flashEmailChanged */
?>
<section class="auth-page">
    <h1>Connexion</h1>
    <p class="lead">Connectez-vous avec votre adresse e-mail ou votre pseudo.</p>

    <?php if ($flashRegistered): ?>
        <p class="alert alert-success">
            Si votre demande est valide, un e-mail de confirmation vous a été envoyé.
            Cliquez sur le lien dans ce message pour poursuivre l’inscription.
        </p>
    <?php endif; ?>
    <?php if ($flashConfirmed): ?>
        <p class="alert alert-success">Votre compte est prêt. Connectez-vous ci-dessous.</p>
    <?php endif; ?>
    <?php if ($flashPendingAdmin): ?>
        <p class="alert alert-success">
            Votre e-mail est confirmé. Un administrateur doit encore valider votre compte.
        </p>
    <?php endif; ?>
    <?php if ($flashAccountDeleted): ?>
        <p class="alert alert-success">Votre compte a été supprimé.</p>
    <?php endif; ?>
    <?php if ($flashEmailChanged): ?>
        <p class="alert alert-success">Votre adresse e-mail a été mise à jour. Connectez-vous avec la nouvelle adresse.</p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/connexion.php" class="auth-form import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <?php if ($redirect !== ''): ?>
            <input type="hidden" name="redirect" value="<?= Moncine\View::escape($redirect) ?>">
        <?php endif; ?>

        <label for="login_identifier">Adresse e-mail ou pseudo</label>
        <input type="text" name="login" id="login_identifier" required autocomplete="username" autofocus
               maxlength="<?= Moncine\UserProfile::MAX_PSEUDO_LENGTH + 120 ?>">

        <label for="login_password">Mot de passe</label>
        <input type="password" name="password" id="login_password" required autocomplete="current-password"
               maxlength="<?= Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH ?>">

        <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>

    <p class="hint">
        <a href="/mot-de-passe-oublie.php">Mot de passe oublié ?</a>
        <?php if ($registrationEnabled): ?>
            · <a href="/inscription.php">Créer un compte</a>
        <?php endif; ?>
    </p>
</section>

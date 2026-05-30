<?php
/** @var string $error */
?>
<section class="auth-page">
    <h1>Bienvenue sur Moncine</h1>
    <p class="lead">
        Créez le compte <strong>administrateur</strong> de cette installation.
        Il pourra gérer le catalogue partagé et les autres comptes.
    </p>

    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/premier-compte.php" class="auth-form import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>

        <label for="setup_nom">Votre nom</label>
        <input type="text" name="nom" id="setup_nom" required autocomplete="name" autofocus>

        <label for="setup_email">Adresse e-mail</label>
        <input type="email" name="email" id="setup_email" required autocomplete="email">

        <label for="setup_password">Mot de passe</label>
        <input type="password" name="password" id="setup_password" required autocomplete="new-password"
               minlength="<?= Moncine\UtilisateurRepository::MIN_PASSWORD_LENGTH ?>"
               maxlength="<?= Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH ?>">
        <p class="hint">8 caractères minimum.</p>

        <label for="setup_password2">Confirmer le mot de passe</label>
        <input type="password" name="password_confirm" id="setup_password2" required autocomplete="new-password"
               minlength="<?= Moncine\UtilisateurRepository::MIN_PASSWORD_LENGTH ?>"
               maxlength="<?= Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH ?>">

        <button type="submit" class="btn btn-primary">Créer le compte administrateur</button>
    </form>
</section>
